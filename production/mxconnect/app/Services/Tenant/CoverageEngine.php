<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ActType;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageException;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\WaitingPeriod;
use Illuminate\Support\Carbon;

/**
 * MOTEUR DE PRISE EN CHARGE (mission « Mutuelle paramétrable », L2, 2026-11-18).
 *
 * Applique les paramètres de la formule (version en vigueur à la date des soins) :
 * garanties par catégorie de soins et catégorie de bénéficiaire, taux et ticket modérateur,
 * délais de carence (accident, urgence, renouvellement continu), exclusions, plafonds par
 * acte / jour / mois / an, par bénéficiaire ou par famille, en montant et/ou en nombre, partagés
 * ou non, et dérogations accordées (« 4 yeux »). Rien n'est codé en dur ; un paramètre absent
 * n'est jamais remplacé par une valeur par défaut.
 *
 * Utilisé pour les versions qui ont des garanties détaillées (GuaranteeVersion::hasCatalog()).
 * Les anciennes versions (taux unique) gardent leur calcul (ClaimAssessmentService), inchangé.
 *
 * La CONSOMMATION des plafonds se fait dans la transaction de la saisie, après verrouillage de la
 * ligne de l'adhérent (ClaimCaptureService) : deux saisies simultanées pour la même famille sont
 * traitées l'une après l'autre (pas de double consommation du même reste).
 */
class CoverageEngine
{
    public const STATUSES = [
        'active'        => 'DROITS ACTIFS',
        'not_activated' => 'DROITS NON ACTIVÉS',
        'waiting'       => 'DÉLAI DE CARENCE EN COURS',
        'limit_reached' => 'PLAFOND ÉPUISÉ',
        'expired'       => 'ADHÉSION EXPIRÉE',
        'suspended'     => 'ADHÉSION SUSPENDUE',
        'not_covered'   => 'GARANTIE NON COUVERTE',
        'authorization' => 'AUTORISATION ADMINISTRATIVE REQUISE',
    ];

    /** Statuts de prestation qui consomment un plafond (les rejetées le libèrent). */
    public const CONSUMING = ['captured', 'controlled', 'validated'];

    // ------------------------------------------------------------------ adhésion

    /**
     * Situation de l'adhésion à une date (sans acte) : statut, motifs, souscription, version.
     *
     * @return array{status:string, reasons:array<string>, subscription:?Subscription, version:?GuaranteeVersion}
     */
    public function membership(Member $member, ?Dependent $dependent, string $date, ?int $providerId = null): array
    {
        $status = $member->status->value ?? (string) $member->status;
        if (in_array($status, ['left', 'revoked'], true)) {
            return $this->m('expired', ['member_inactive']);
        }
        if ($dependent) {
            if ($dependent->status !== 'active' || ($dependent->left_on && $dependent->left_on->toDateString() < $date)) {
                return $this->m('expired', ['dependent_removed']);
            }
            if ($dependent->joined_on && $dependent->joined_on->toDateString() > $date) {
                return $this->m('not_activated', ['dependent_not_active_yet']);
            }
        }
        if ($status === 'suspended') {
            return $this->m('suspended', ['member_inactive']);
        }
        $subscription = $member->subscriptions()->where('status', 'validated')->whereDate('effective_date', '<=', $date)->latest('effective_date')->first();
        if (! $subscription) {
            // Souscription saisie mais pas encore validée, ou validée avec une date d'effet future :
            // droits NON ACTIVÉS (jamais couverts automatiquement). Sinon : adhésion expirée/absente.
            $pending = $member->subscriptions()->where(fn ($q) => $q->where('status', 'captured')
                ->orWhere(fn ($w) => $w->where('status', 'validated')->whereDate('effective_date', '>', $date)))->exists();

            return $this->m($pending ? 'not_activated' : 'expired', [$pending ? 'subscription_pending' : 'no_active_guarantee']);
        }
        $version = GuaranteeVersion::applicableOn((int) $subscription->guaranteeVersion?->guarantee_id, $date);
        if (! $version) {
            return $this->m('expired', ['no_active_guarantee'], $subscription);
        }
        $arrears = ContributionSchedule::where('subscription_id', $subscription->id)
            ->where(fn ($w) => $w->where('status', 'overdue')->orWhere(fn ($x) => $x->where('status', 'to_pay')->whereDate('due_date', '<', now()->toDateString())))
            ->exists();
        if ($arrears) {
            return $this->m('suspended', ['contribution_arrears'], $subscription, $version);
        }
        if ($providerId && is_array($version->provider_network) && ! in_array($providerId, $version->provider_network, true)) {
            return $this->m('not_covered', ['provider_not_authorised'], $subscription, $version);
        }

        return $this->m('active', [], $subscription, $version);
    }

    // ------------------------------------------------------------------ évaluation d'un acte

    /**
     * Évalue une prestation (rien n'est enregistré). $opt : emergency, accident (bool).
     *
     * @return array<string,mixed>
     */
    public function evaluate(Member $member, ?Dependent $dependent, int $actTypeId, int $totalMinor, string $date, ?int $providerId = null, array $opt = []): array
    {
        $ms = $this->membership($member, $dependent, $date, $providerId);
        if ($ms['status'] !== 'active') {
            return $this->refusal($ms['status'], $ms['reasons'], $totalMinor, $ms['version']);
        }
        $version = $ms['version'];
        $act = ActType::find($actTypeId);
        $ctx = [
            'date' => $date, 'act' => $act, 'total' => $totalMinor,
            'categories' => $this->beneficiaryCategories($member, $dependent, $ms['subscription'], $version, $date),
            'coverage_start' => $this->coverageStart($member, $dependent, $ms['subscription'], $version),
            'emergency' => (bool) ($opt['emergency'] ?? false), 'accident' => (bool) ($opt['accident'] ?? false),
            'exceptions' => $this->exceptions($member, $dependent, $date),
            'consumed' => fn (CoverageLimit $l, CoverageRule $r) => $this->consumed($l, $member, $dependent, $date, $version, $opt['exclude_prestation'] ?? null),
            'excluded' => (int) ($opt['excluded_minor'] ?? 0),
            'skip_authorization' => (bool) ($opt['skip_authorization'] ?? false),
            'authorization' => fn (CoverageRule $r) => $this->authorizationFor($member, $dependent, $act, $r, $date, $providerId, $opt['preauthorization_id'] ?? null),
        ];

        return $this->compute($version, $ctx);
    }

    /**
     * Calcul pur à partir d'un contexte (aussi utilisé par le SIMULATEUR du catalogue, sans adhérent).
     * $ctx : date, act (ActType), total, categories (ordre du plus précis au plus large), coverage_start
     * (Carbon), emergency, accident, exceptions (collection), consumed (callable → [montant, nombre]).
     */
    public function compute(GuaranteeVersion $version, array $ctx): array
    {
        $act = $ctx['act'];
        $total = (int) $ctx['total'];
        $exceptions = collect($ctx['exceptions'] ?? []);
        $matches = fn ($e) => (! $e->service_category_id || $e->service_category_id === $act?->service_category_id) && (! $e->act_type_id || $e->act_type_id === $act?->id);

        // 1) Garantie applicable.
        $rule = $this->ruleFor($version, $act, $ctx['categories']);
        if (! $rule) {
            return $this->refusal('not_covered', ['no_rule'], $total, $version);
        }

        // 2) Exclusions de la formule (sauf dérogation accordée).
        $excluded = $act && is_array($version->exclusions) && in_array($act->id, $version->exclusions, true);
        $usedException = null;
        if ($excluded) {
            $usedException = $exceptions->first(fn ($e) => $e->kind === 'exclusion_override' && $matches($e));
            if (! $usedException) {
                return $this->refusal('not_covered', ['act_excluded'], $total, $version, $rule);
            }
        }

        // 3) Délai de carence.
        $waitDays = $this->waitingDays($version, $rule, $ctx['categories'], $ctx['accident'] ?? false);
        $waitingUntil = null;
        if ($waitDays > 0 && ! (($ctx['emergency'] ?? false) && $rule->emergency_exempt)) {
            $end = Carbon::parse($ctx['coverage_start'])->startOfDay()->addDays($waitDays);
            if (Carbon::parse($ctx['date'])->lt($end)) {
                $waiver = $exceptions->first(fn ($e) => $e->kind === 'waiting_waiver' && $matches($e));
                if (! $waiver) {
                    $r = $this->refusal('waiting', ['waiting_period'], $total, $version, $rule);
                    $r['waiting_until'] = $end->toDateString();
                    $r['waiting_days'] = $waitDays;

                    return $r;
                }
                $usedException ??= $waiver;
                $waitingUntil = $end->toDateString();
            }
        }

        // 3 bis) Accord préalable (2026-11-19). Sans accord : AUTORISATION ADMINISTRATIVE REQUISE, sauf
        // URGENCE — les soins ne sont jamais bloqués ; la prestation est marquée « à régulariser ».
        $authorization = null;
        $authMissing = false;
        if (! ($ctx['skip_authorization'] ?? false) && $rule->needsAuthorization($total)) {
            $authorization = isset($ctx['authorization']) ? ($ctx['authorization'])($rule) : null;
            if (! $authorization) {
                if ($ctx['emergency'] ?? false) {
                    $authMissing = true;
                } else {
                    $r = $this->refusal('authorization', ['authorization_required'], $total, $version, $rule);
                    $r['authorization_review'] = $rule->authorization_review;

                    return $r;
                }
            }
        }

        // 4) Montants : éligible (hors lignes non prises en charge, puis tarif conventionnel si la
        // garantie le prévoit), part au taux.
        $tariff = (int) ($act?->tariff_minor ?? 0);
        $billedEligible = max(0, $total - (int) ($ctx['excluded'] ?? 0));
        $eligible = $rule->tariff_cap && $tariff > 0 ? min($billedEligible, $tariff) : $billedEligible;
        $base = (int) round($eligible * ((float) $rule->coverage_rate) / 100);

        // 5) Plafonds (montant et/ou nombre) applicables à cette garantie.
        $limits = [];
        $exhaustedByCount = false;
        $capByLimits = $base;
        foreach ($version->limits()->where('active', true)->orderBy('id')->get() as $l) {
            if (! $l->appliesTo($rule)) {
                continue;
            }
            [$usedAmount, $usedCount] = $l->scope === 'act' ? [0, 0] : ($ctx['consumed'])($l, $rule);
            $row = ['limit' => $l, 'used_amount' => $usedAmount, 'used_count' => $usedCount, 'remaining' => null, 'remaining_count' => null];
            if ($l->max_count !== null && $l->scope !== 'act') {
                $row['remaining_count'] = max(0, $l->max_count - $usedCount);
                $exhaustedByCount = $exhaustedByCount || $row['remaining_count'] <= 0;
            }
            if ($l->amount_minor !== null) {
                $row['remaining'] = max(0, $l->amount_minor - $usedAmount);
                $capByLimits = min($capByLimits, $row['remaining']);
            }
            $limits[] = $row;
        }
        if ($exhaustedByCount) {
            $capByLimits = 0;
        }
        $mutual = min($base, $capByLimits);

        // Plafond supplémentaire accordé par dérogation : s'AJOUTE au reste, la règle n'est pas modifiée.
        $extraUsed = 0;
        if ($mutual < $base) {
            $extra = $exceptions->first(fn ($e) => $e->kind === 'limit_extra' && $matches($e) && $e->remainingMinor() > 0);
            if ($extra) {
                $extraUsed = min($base - $mutual, $extra->remainingMinor());
                $mutual += $extraUsed;
                $usedException ??= $extra;
            }
        }
        if ($mutual <= 0 && $base > 0) {
            $r = $this->refusal('limit_reached', [$exhaustedByCount ? 'count_reached' : 'ceiling_reached'], $total, $version, $rule);
            $r['limits'] = $this->limitView($limits, 0);

            return $r;
        }

        // Accord préalable avec un montant accordé : la part de la mutuelle ne le dépasse pas.
        if ($authorization && $authorization->approved_minor !== null) {
            $mutual = min($mutual, (int) $authorization->approved_minor);
        }
        $overLimit = $base - $mutual;

        return [
            'status' => 'active', 'label' => self::STATUSES['active'], 'reasons' => [], 'ok' => true,
            'version' => $version, 'rule' => $rule,
            'rate' => (float) $rule->coverage_rate, 'copay_rate' => $rule->copay_rate,
            'billed' => $total, 'eligible' => $eligible,
            'mutual' => $mutual, 'copay' => $eligible - $base,
            'non_covered' => ($total - $eligible) + $overLimit, 'over_limit' => $overLimit, 'patient' => $total - $mutual,
            'limits' => $this->limitView($limits, $mutual), 'tariff' => $tariff ?: null,
            'waiting_days' => $waitDays, 'waiting_until' => $waitingUntil,
            'exception' => $usedException, 'exception_extra_used' => $extraUsed,
            'authorization' => $authorization, 'authorization_missing' => $authMissing,
        ];
    }

    /**
     * Accord préalable utilisable pour ces soins : accordé, non utilisé, valide à la date, pour ce
     * bénéficiaire, cet acte (ou cette garantie) et, si connu, cet établissement.
     */
    public function authorizationFor(Member $member, ?Dependent $dependent, ?ActType $act, CoverageRule $rule, string $date, ?int $providerId, ?int $preauthId = null): ?\App\Models\Tenant\Preauthorization
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('preauthorizations')) {
            return null;
        }
        $ruleIds = CoverageRule::where('rule_key', $rule->rule_key)->pluck('id');

        return \App\Models\Tenant\Preauthorization::where('member_id', $member->id)->where('status', 'approved')->whereNull('prestation_id')
            ->when($dependent, fn ($q) => $q->where('dependent_id', $dependent->id), fn ($q) => $q->whereNull('dependent_id'))
            ->when($preauthId, fn ($q) => $q->whereKey($preauthId))
            ->when($providerId, fn ($q) => $q->where('provider_id', $providerId))
            ->where(fn ($q) => $q->where('act_type_id', $act?->id)->orWhereIn('coverage_rule_id', $ruleIds))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->orderBy('id')->first();
    }

    // ------------------------------------------------------------------ éléments du calcul

    /** Garantie la plus précise : catégorie de soins exacte avant « toutes », bénéficiaire le plus précis. */
    public function ruleFor(GuaranteeVersion $version, ?ActType $act, array $categories): ?CoverageRule
    {
        $rules = CoverageRule::where('guarantee_version_id', $version->id)->where('active', true)->orderBy('sort')->orderBy('id')->get();
        $catId = $act?->service_category_id;
        // Catégorie de soins exacte d'abord, puis garantie « toutes prestations » (catégorie vide).
        foreach ($catId ? [$catId, null] : [null] as $serviceCat) {
            foreach ($categories as $bc) {
                $hit = $rules->first(fn ($r) => $r->service_category_id === $serviceCat && $r->beneficiary_category === $bc);
                if ($hit) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /** Catégories du bénéficiaire, de la plus précise à la plus large (« all » en dernier). */
    public function beneficiaryCategories(Member $member, ?Dependent $dependent, ?Subscription $sub, GuaranteeVersion $version, string $date): array
    {
        $person = $dependent ?? $member;
        $age = $person->birth_date ? Carbon::parse($person->birth_date)->diffInYears(Carbon::parse($date)) : null;

        return self::categoriesFor(
            age: $age === null ? null : (int) floor($age),
            seniorAge: $version->senior_age,
            student: (bool) ($dependent?->is_student ?? false),
            newSpouse: $dependent && $dependent->relationship === 'spouse' && $sub
                && ($dependent->joined_on ?? $dependent->created_at)?->toDateString() > Carbon::parse($sub->effective_date)->toDateString(),
        );
    }

    public static function categoriesFor(?int $age, ?int $seniorAge, bool $student = false, bool $newSpouse = false): array
    {
        $c = [];
        if ($newSpouse) {
            $c[] = 'new_spouse';
        }
        if ($student) {
            $c[] = 'student';
        }
        if ($age !== null) {
            if ($age <= 5) {
                $c[] = 'child_0_5';
            } elseif ($age <= 17) {
                $c[] = 'child_6_17';
            } else {
                if ($seniorAge && $age >= $seniorAge) {
                    $c[] = 'senior';
                }
                $c[] = 'adult';
            }
        }
        $c[] = 'all';

        return array_values(array_unique($c));
    }

    /**
     * Début de la couverture pour la carence : date d'effet de la souscription (ou d'intégration de
     * l'ayant droit, si plus tardive) ; avec renouvellement continu, la date de 1re adhésion.
     */
    public function coverageStart(Member $member, ?Dependent $dependent, Subscription $sub, GuaranteeVersion $version): Carbon
    {
        // Changement de formule avec ancienneté conservée (2026-11-23) : la carence part de l'ancienne date de début.
        $start = $version->renewal_waiver && $member->joined_at ? Carbon::parse($member->joined_at)
            : Carbon::parse($sub->waiting_from ?? $sub->effective_date);
        $joined = $dependent ? ($dependent->joined_on ?? $dependent->created_at) : null;
        if ($joined && Carbon::parse($joined)->gt($start)) {
            $start = Carbon::parse($joined);
        }

        return $start->startOfDay();
    }

    /** Délai de carence le plus précis (garantie + catégorie de bénéficiaire), sinon délai général. */
    public function waitingDays(GuaranteeVersion $version, CoverageRule $rule, array $categories, bool $accident = false): int
    {
        if ($accident && $rule->accident_waiting_days !== null) {
            return (int) $rule->accident_waiting_days;
        }
        $rows = WaitingPeriod::where('guarantee_version_id', $version->id)->get();
        foreach ([$rule->rule_key, null] as $key) {
            foreach ($categories as $bc) {
                $hit = $rows->first(fn ($w) => $w->rule_key === $key && $w->beneficiary_category === $bc);
                if ($hit) {
                    return (int) $hit->days;
                }
            }
        }

        return (int) $version->observation_days;
    }

    /** Dérogations accordées en cours de validité pour ce bénéficiaire. */
    public function exceptions(Member $member, ?Dependent $dependent, string $date)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('coverage_exceptions')) {
            return collect();
        }

        return CoverageException::where('member_id', $member->id)->where('status', 'approved')
            ->when($dependent, fn ($q) => $q->where('dependent_id', $dependent->id), fn ($q) => $q->whereNull('dependent_id'))
            ->whereDate('valid_from', '<=', $date)->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderBy('id')->get();
    }

    /**
     * Consommation d'un plafond : parts mutuelle et nombre de prestations en cours ou validées, sur la
     * fenêtre du plafond (jour, mois, année civile), pour le bénéficiaire ou toute la famille, et pour
     * les garanties concernées (par rule_key, toutes versions de la formule confondues).
     *
     * @return array{0:int,1:int}
     */
    public function consumed(CoverageLimit $l, Member $member, ?Dependent $dependent, string $date, GuaranteeVersion $version, ?int $excludePrestation = null): array
    {
        $d = Carbon::parse($date);
        $q = Prestation::query()->whereIn('status', self::CONSUMING)
            ->when($excludePrestation, fn ($w) => $w->where('id', '!=', $excludePrestation))
            ->whereHas('careClaim', fn ($w) => $w->where('member_id', $member->id)
                ->when($l->holder === 'beneficiary', fn ($x) => $dependent ? $x->where('dependent_id', $dependent->id) : $x->whereNull('dependent_id')));
        match ($l->scope) {
            'day'   => $q->whereDate('care_date', $d->toDateString()),
            'month' => $q->whereYear('care_date', $d->year)->whereMonth('care_date', $d->month),
            default => $q->whereYear('care_date', $d->year),
        };
        $versionIds = GuaranteeVersion::where('guarantee_id', $version->guarantee_id)->pluck('id');
        if ($l->rule_keys) {
            $q->whereIn('coverage_rule_id', CoverageRule::whereIn('rule_key', $l->rule_keys)->whereIn('guarantee_version_id', $versionIds)->select('id'));
        } else {
            $q->whereIn('guarantee_version_id', $versionIds);
        }

        return [(int) (clone $q)->sum('mutual_part_minor'), (int) $q->count()];
    }

    // ------------------------------------------------------------------ résultats

    private function m(string $status, array $reasons, ?Subscription $sub = null, ?GuaranteeVersion $version = null): array
    {
        return ['status' => $status, 'reasons' => $reasons, 'subscription' => $sub, 'version' => $version];
    }

    private function refusal(string $status, array $reasons, int $total, ?GuaranteeVersion $version = null, ?CoverageRule $rule = null): array
    {
        return [
            'status' => $status, 'label' => self::STATUSES[$status] ?? $status, 'reasons' => $reasons, 'ok' => false,
            'version' => $version, 'rule' => $rule, 'rate' => $rule ? (float) $rule->coverage_rate : null, 'copay_rate' => $rule?->copay_rate,
            'billed' => $total, 'eligible' => 0, 'mutual' => 0, 'copay' => 0, 'non_covered' => $total, 'over_limit' => 0, 'patient' => $total,
            'limits' => [], 'tariff' => null, 'waiting_days' => null, 'waiting_until' => null, 'exception' => null, 'exception_extra_used' => 0,
            'authorization' => null, 'authorization_missing' => false, 'authorization_review' => null,
        ];
    }

    private function limitView(array $limits, int $mutual): array
    {
        return array_map(fn ($r) => [
            'label' => $r['limit']->label ?: 'Plafond',
            'scope' => CoverageLimit::SCOPES[$r['limit']->scope] ?? $r['limit']->scope,
            'holder' => CoverageLimit::HOLDERS[$r['limit']->holder] ?? $r['limit']->holder,
            'amount' => $r['limit']->amount_minor, 'max_count' => $r['limit']->max_count,
            'used_amount' => $r['used_amount'], 'used_count' => $r['used_count'],
            'remaining_before' => $r['remaining'], 'remaining_after' => $r['remaining'] === null ? null : max(0, $r['remaining'] - $mutual),
            'remaining_count' => $r['remaining_count'],
        ], $limits);
    }
}
