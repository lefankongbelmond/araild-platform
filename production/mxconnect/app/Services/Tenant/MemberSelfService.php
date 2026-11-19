<?php

namespace App\Services\Tenant;

use App\Models\Central\CareProvider;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\Complaint;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\MemberChangeRequest;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Services\ClaimAssessmentService;
use App\Services\ClaimCaptureService;
use Illuminate\Support\Facades\Schema;

/**
 * PORTAIL ADHÉRENT — lecture, DANS la mutuelle, des données d'UN adhérent (2026-11-20).
 * Appelé depuis le compte central MX-CONNECT via $mutual->run(). Toutes les requêtes sont filtrées
 * sur cet adhérent (et ses ayants droit) : jamais d'autre adhérent, jamais d'autre mutuelle.
 * Les valeurs viennent des paramètres de la formule (moteur de prise en charge) : rien n'est inventé.
 */
class MemberSelfService
{
    public const PRESTATION_STATUS = ['captured' => 'En cours de traitement', 'controlled' => 'En cours de contrôle', 'validated' => 'Prise en charge validée', 'rejected' => 'Rejetée'];
    public const SCHEDULE_STATUS = ['to_pay' => 'À payer', 'overdue' => 'En retard', 'paid' => 'Payée', 'partial' => 'Partiellement payée'];

    public function __construct(private CoverageEngine $engine, private ClaimCaptureService $capture, private ClaimAssessmentService $assessment, private CoverageService $coverage) {}

    // ------------------------------------------------------------------ tableau de bord

    public function dashboard(Member $m): array
    {
        $today = now()->toDateString();
        $ms = $this->engine->membership($m, null, $today);
        $sub = $ms['subscription'] ?? $m->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
        $version = $ms['version'] ?? ($sub ? GuaranteeVersion::applicableOn((int) $sub->guaranteeVersion?->guarantee_id, $today) : null);
        $subIds = $m->subscriptions()->where('status', 'validated')->pluck('id');
        $overdue = ContributionSchedule::whereIn('subscription_id', $subIds)
            ->where(fn ($w) => $w->where('status', 'overdue')->orWhere(fn ($x) => $x->where('status', 'to_pay')->whereDate('due_date', '<', $today)));
        $next = ContributionSchedule::whereIn('subscription_id', $subIds)->whereIn('status', ['to_pay', 'partial'])
            ->whereDate('due_date', '>=', $today)->orderBy('due_date')->first();
        $year = now()->year;
        $prest = $this->prestationsQuery($m)->whereYear('care_date', $year)->where('status', '!=', 'rejected');

        return [
            'member' => $m, 'status' => $ms['status'], 'status_label' => CoverageEngine::STATUSES[$ms['status']] ?? $ms['status'], 'reasons' => $ms['reasons'],
            'subscription' => $sub, 'version' => $version, 'formula' => $version?->guarantee?->name ?? $sub?->guaranteeVersion?->guarantee?->name,
            'coverage_start' => $sub?->effective_date, 'coverage_end' => $version?->valid_to,
            'beneficiaries' => 1 + $m->dependents()->where('status', 'active')->count(),
            'overdue_count' => (clone $overdue)->count(), 'overdue_minor' => (int) (clone $overdue)->sum('due_minor'),
            'grace_until' => \App\Support\ArrearsPolicy::graceEnd((clone $overdue)->min('due_date')),
            'next_due' => $next ? ['date' => $next->due_date, 'amount' => (int) $next->due_minor, 'label' => $next->label()] : null,
            'prestations_count' => (clone $prest)->count(), 'mutual_year_minor' => (int) (clone $prest)->sum('mutual_part_minor'),
            'limits' => $version ? $this->limits($m, null, $version, $sub) : [],
            'pending_requests' => MemberChangeRequest::where('member_id', $m->id)->where('status', 'requested')->count(),
        ];
    }

    /**
     * Plafonds de l'adhérent : montant, consommé, reste (formule paramétrée : plafonds annuels et
     * mensuels ; formule classique : plafonds annuels).
     */
    public function limits(Member $m, ?Dependent $d, GuaranteeVersion $v, $sub): array
    {
        $today = now()->toDateString();
        $out = [];
        if ($v->hasCatalog()) {
            $names = CoverageRule::where('guarantee_version_id', $v->id)->pluck('label', 'rule_key');
            foreach (CoverageLimit::where('guarantee_version_id', $v->id)->where('active', true)->whereIn('scope', ['year', 'month'])->orderBy('id')->get() as $l) {
                [$used, $count] = $this->engine->consumed($l, $m, $d, $today, $v);
                $out[] = [
                    'label' => $l->label ?: ($l->rule_keys ? collect($l->rule_keys)->map(fn ($k) => $names[$k] ?? '')->filter()->implode(', ') : 'Toutes les garanties'),
                    'period' => CoverageLimit::SCOPES[$l->scope] . ', ' . CoverageLimit::HOLDERS[$l->holder],
                    'amount' => $l->amount_minor, 'used' => $used, 'remaining' => $l->amount_minor !== null ? max(0, $l->amount_minor - $used) : null,
                    'max_count' => $l->max_count, 'used_count' => $count,
                ];
            }
        } else {
            foreach (Ceiling::with('actType')->where('guarantee_version_id', $v->id)->where('period', 'annual')->get() as $c) {
                $remaining = $this->assessment->remaining($c, $v->id, (int) ($c->act_type_id ?? 0), $m->id, $today);
                $out[] = ['label' => $c->actType?->label ?? 'Tous les soins', 'period' => 'par année civile', 'amount' => (int) $c->ceiling_minor,
                    'used' => max(0, (int) $c->ceiling_minor - $remaining), 'remaining' => $remaining, 'max_count' => null, 'used_count' => null];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ garanties

    public function guarantees(Member $m): array
    {
        $today = now()->toDateString();
        $sub = $m->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
        $v = $sub ? GuaranteeVersion::applicableOn((int) $sub->guaranteeVersion?->guarantee_id, $today) : null;
        if (! $v) {
            return ['version' => null];
        }
        $catalog = $v->hasCatalog();
        $rules = [];
        if ($catalog) {
            $start = $this->engine->coverageStart($m, null, $sub, $v);
            foreach ($v->rules()->with('category')->where('active', true)->get() as $r) {
                $cats = [$r->beneficiary_category];
                $days = $this->engine->waitingDays($v, $r, array_merge($cats, ['all']));
                $rules[] = [
                    'label' => $r->label, 'category' => $r->category?->label ?? 'Tous les soins',
                    'who' => CoverageRule::BENEFICIARY_CATEGORIES[$r->beneficiary_category] ?? $r->beneficiary_category,
                    'rate' => (float) $r->coverage_rate, 'copay' => $r->copay_rate !== null ? (float) $r->copay_rate : max(0, 100 - (float) $r->coverage_rate),
                    'waiting_days' => $days, 'waiting_until' => $days > 0 ? $start->copy()->addDays($days)->toDateString() : null,
                    'emergency_exempt' => $r->emergency_exempt, 'tariff_cap' => $r->tariff_cap,
                    'authorization' => (bool) ($r->requires_authorization ?? false),
                    'authorization_above' => $r->authorization_above_minor ?? null,
                    'accident_days' => $r->accident_waiting_days, 'conditions' => $r->conditions,
                ];
            }
        }
        $limitRows = [];
        if ($catalog) {
            $names = CoverageRule::where('guarantee_version_id', $v->id)->pluck('label', 'rule_key');
            foreach ($v->limits()->where('active', true)->orderBy('id')->get() as $l) {
                $limitRows[] = ['label' => $l->label ?: 'Plafond', 'amount' => $l->amount_minor, 'max_count' => $l->max_count,
                    'scope' => CoverageLimit::SCOPES[$l->scope] ?? $l->scope, 'holder' => CoverageLimit::HOLDERS[$l->holder] ?? $l->holder,
                    'for' => $l->rule_keys ? collect($l->rule_keys)->map(fn ($k) => $names[$k] ?? '')->filter()->implode(', ') : 'toutes les garanties'];
            }
        } else {
            foreach (Ceiling::with('actType')->where('guarantee_version_id', $v->id)->get() as $c) {
                $limitRows[] = ['label' => $c->actType?->label ?? 'Tous les soins', 'amount' => (int) $c->ceiling_minor, 'max_count' => null,
                    'scope' => Ceiling::PERIODS[$c->period] ?? $c->period, 'holder' => 'par adhérent', 'for' => $c->actType?->label ?? 'tous les soins'];
            }
        }

        return [
            'version' => $v, 'formula' => $v->guarantee?->name, 'catalog' => $catalog, 'rules' => $rules, 'limits' => $limitRows,
            'rate' => $catalog ? null : (float) $v->coverage_rate, 'copay' => $catalog ? null : (float) $v->copay_rate,
            'observation_days' => (int) $v->observation_days, 'observation_ends_on' => $catalog ? null : $sub?->observation_ends_on,
            'exclusions' => is_array($v->exclusions) && $v->exclusions ? ActType::withTrashed()->whereIn('id', $v->exclusions)->orderBy('label')->pluck('label')->all() : [],
            'network' => is_array($v->provider_network) ? count($v->provider_network) : null,
            'renewal_waiver' => (bool) $v->renewal_waiver, 'senior_age' => $v->senior_age,
        ];
    }

    // ------------------------------------------------------------------ prestations

    private function prestationsQuery(Member $m)
    {
        return Prestation::whereHas('careClaim', fn ($q) => $q->where('member_id', $m->id));
    }

    public function prestations(Member $m, ?int $year = null)
    {
        $items = $this->prestationsQuery($m)->with(['careClaim.dependent', 'actType'])
            ->when($year, fn ($q) => $q->whereYear('care_date', $year))->latest('care_date')->latest('id')->paginate(20)->withQueryString();
        $providers = CareProvider::on('central')->whereIn('id', $items->pluck('provider_id')->unique())->pluck('name', 'id');

        return ['items' => $items, 'providers' => $providers];
    }

    public function prestation(Member $m, int $id): array
    {
        $p = $this->prestationsQuery($m)->with(['careClaim.dependent', 'actType'])->whereKey($id)->firstOrFail();
        $dispensation = Schema::hasTable('dispensations') ? \App\Models\Tenant\Dispensation::with(['lines.medicine', 'prescription'])->where('prestation_id', $p->id)->first() : null;
        $preauth = $p->preauthorization_id ?? null ? \App\Models\Tenant\Preauthorization::find($p->preauthorization_id) : null;

        return ['p' => $p, 'provider' => CareProvider::on('central')->find($p->provider_id)?->name, 'dispensation' => $dispensation, 'preauth' => $preauth];
    }

    // ------------------------------------------------------------------ cotisations

    public function contributions(Member $m): array
    {
        $subIds = $m->subscriptions()->where('status', 'validated')->pluck('id');
        $schedules = ContributionSchedule::whereIn('subscription_id', $subIds)->withSum('payments as paid_minor', 'amount_minor')
            ->orderByDesc('due_date')->limit(36)->get();
        $payments = ContributionPayment::withTrashed()->whereIn('schedule_id', ContributionSchedule::whereIn('subscription_id', $subIds)->select('id'))
            ->latest('paid_on')->latest('id')->limit(50)->get();
        $today = now()->toDateString();

        return [
            'schedules' => $schedules, 'payments' => $payments,
            'expected' => (int) $schedules->sum('due_minor'), 'paid' => (int) $schedules->sum(fn ($s) => (int) $s->paid_minor),
            'overdue' => $schedules->filter(fn ($s) => $s->status === 'overdue' || ($s->status === 'to_pay' && $s->due_date?->toDateString() < $today)),
        ];
    }

    /** Reçu d'un paiement de CET adhérent (collection_ref : un reçu par encaissement). */
    public function receipt(Member $m, int $paymentId): array
    {
        $subIds = $m->subscriptions()->pluck('id');
        $p = ContributionPayment::withTrashed()->with('schedule')->whereKey($paymentId)
            ->whereHas('schedule', fn ($q) => $q->whereIn('subscription_id', $subIds))->firstOrFail();
        $group = $p->collection_ref ?? null
            ? ContributionPayment::withTrashed()->with('schedule')->where('collection_ref', $p->collection_ref)->get()
            : collect([$p]);

        return ['payment' => $p, 'lines' => $group, 'total' => (int) $group->sum('amount_minor'), 'cancelled' => $p->trashed()];
    }

    // ------------------------------------------------------------------ bénéficiaires et demandes

    public function dependents(Member $m): array
    {
        $v = $this->capture->versionFor($m, now()->toDateString());

        return [
            'dependents' => $m->dependents()->withTrashed()->orderBy('status')->orderBy('first_name')->get(),
            'requests' => MemberChangeRequest::where('member_id', $m->id)->latest('id')->limit(30)->get(),
            'limits' => fn (Dependent $d) => $v ? $this->limits($m, $d, $v, null) : [],
            'consumed' => fn (Dependent $d) => (int) Prestation::whereHas('careClaim', fn ($q) => $q->where('member_id', $m->id)->where('dependent_id', $d->id))
                ->whereYear('care_date', now()->year)->where('status', '!=', 'rejected')->sum('mutual_part_minor'),
        ];
    }

    public function requestChange(Member $m, int $accountId, string $kind, array $payload, ?int $dependentId = null): MemberChangeRequest
    {
        if ($dependentId !== null) {
            abort_unless($m->dependents()->whereKey($dependentId)->exists(), 404);
        }
        abort_if(MemberChangeRequest::where('member_id', $m->id)->where('status', 'requested')->where('kind', $kind)
            ->where('dependent_id', $dependentId)->exists(), 422, 'Une demande du même type est déjà en attente.');

        return MemberChangeRequest::create(['member_id' => $m->id, 'public_account_id' => $accountId, 'kind' => $kind,
            'dependent_id' => $dependentId, 'payload' => $payload, 'status' => 'requested']);
    }

    // ------------------------------------------------------------------ annuaire des prestataires

    public function providers(Member $m, array $f): array
    {
        $v = $this->capture->versionFor($m, now()->toDateString());
        $network = $v && is_array($v->provider_network) ? $v->provider_network : null;
        $ids = ProviderConvention::where('active', true)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()))
            ->pluck('care_provider_id');
        $q = CareProvider::on('central')->whereIn('id', $ids)->where('active', true)
            ->when($network !== null, fn ($w) => $w->whereIn('id', $network))
            ->when($f['type'] ?? null, fn ($w, $t) => $w->where('type', $t))
            ->when($f['ville'] ?? null, fn ($w, $c) => $w->where('locality', $c))
            ->when($f['q'] ?? null, fn ($w, $s) => $w->where('name', 'like', '%' . $s . '%'));
        $all = CareProvider::on('central')->whereIn('id', $ids)->where('active', true)->when($network !== null, fn ($w) => $w->whereIn('id', $network));

        return [
            'items' => $q->orderBy('name')->get(),
            'cities' => (clone $all)->whereNotNull('locality')->distinct()->orderBy('locality')->pluck('locality'),
            'restricted' => $network !== null,
        ];
    }

    // ------------------------------------------------------------------ réclamations

    public function complaints(Member $m)
    {
        return Complaint::where('member_id', $m->id)->latest('id')->limit(50)->get();
    }
}
