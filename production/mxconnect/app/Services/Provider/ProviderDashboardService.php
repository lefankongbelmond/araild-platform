<?php

namespace App\Services\Provider;

use App\Models\Central\CareProvider;
use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Provider\ProviderAffiliation;
use App\Models\Provider\ProviderDocument;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tableau de bord de l'espace prestataire (2026-11-16) : chiffres RÉELS lus dans la base de
 * chaque mutuelle conventionnée ($mutual->run(), une mutuelle à la fois), jamais estimés.
 *
 *  - Les nombres s'additionnent entre mutuelles ; les MONTANTS restent par devise (aucune
 *    conversion : XAF et XOF ne sont jamais mélangés).
 *  - Facture « acceptée » = en traitement, mise en paiement ou payée ; « payée » = paiement
 *    enregistré par le trésorier de la mutuelle (une facture acceptée n'est pas payée).
 *  - Résultat mis en cache 2 minutes par établissement et par mutuelle (hébergement mutualisé).
 */
class ProviderDashboardService
{
    public const MONTHS = 12;

    public function __construct(private ProviderSpaceService $space) {}

    public function build(CareProvider $provider): array
    {
        $months = collect(range(self::MONTHS - 1, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))->all();
        $out = [
            'mutuals' => [], 'errors' => [], 'months' => $months,
            'counts' => array_fill_keys(['patients_today', 'patients_month', 'pending', 'validated', 'rejected', 'consultations',
                'inv_total', 'inv_accepted', 'inv_rejected', 'inv_paid'], 0),
            'money' => [], 'series' => ['care' => array_fill_keys($months, 0), 'invoices' => array_fill_keys($months, 0)],
            'paidSeries' => [], 'byMutual' => [], 'byAct' => [], 'delay' => ['days' => 0, 'n' => 0],
            'alerts' => [],
        ];

        foreach ($this->space->conventionedMutuals($provider) as $mutual) {
            $currency = Currency::on('central')->find($mutual->currency_id);
            $code = $currency?->code ?? '—';
            try {
                $d = Cache::remember("prov-dash:{$provider->id}:{$mutual->id}", 120, fn () => $mutual->run(fn () => $this->collect($provider->id, $months)));
            } catch (\Throwable $e) {
                report($e);
                $out['errors'][] = $mutual->name;   // une mutuelle injoignable n'empêche pas les autres
                continue;
            }
            $out['mutuals'][] = ['mutual' => $mutual, 'currency' => $currency, 'data' => $d];

            foreach ($out['counts'] as $k => $v) {
                $out['counts'][$k] = $v + ($d['counts'][$k] ?? 0);
            }
            $m = $out['money'][$code] ?? ['currency' => $currency, 'billed' => 0, 'accepted' => 0, 'paid' => 0, 'due' => 0, 'rejected' => 0, 'tobill' => 0];
            foreach (['billed', 'accepted', 'paid', 'due', 'rejected', 'tobill'] as $k) {
                $m[$k] += $d['money'][$k];
            }
            $out['money'][$code] = $m;
            foreach ($months as $ym) {
                $out['series']['care'][$ym] += $d['series']['care'][$ym] ?? 0;
                $out['series']['invoices'][$ym] += $d['series']['invoices'][$ym] ?? 0;
                $out['paidSeries'][$code][$ym] = ($out['paidSeries'][$code][$ym] ?? 0) + ($d['series']['paid'][$ym] ?? 0);
            }
            $out['byMutual'][$mutual->name] = $d['careTotal'];
            foreach ($d['byAct'] as $label => $n) {
                $out['byAct'][$label] = ($out['byAct'][$label] ?? 0) + $n;
            }
            $out['delay']['days'] += $d['delay']['days'];
            $out['delay']['n'] += $d['delay']['n'];
            foreach ($d['alerts'] as $a) {
                $out['alerts'][] = $a + ['mutual' => $mutual->name, 'currency' => $currency];
            }
        }

        arsort($out['byAct']);
        $out['byAct'] = array_slice($out['byAct'], 0, 8, true);
        arsort($out['byMutual']);
        $decided = $out['counts']['inv_accepted'] + $out['counts']['inv_rejected'];
        $out['rejectRate'] = $decided > 0 ? round(100 * $out['counts']['inv_rejected'] / $decided, 1) : null;
        $out['avgDelay'] = $out['delay']['n'] > 0 ? round($out['delay']['days'] / $out['delay']['n'], 1) : null;
        $out['alerts'] = array_merge($this->centralAlerts($provider), $out['alerts']);

        return $out;
    }

    /** Lecture DANS la base d'une mutuelle (contexte tenant initialisé). */
    private function collect(int $pid, array $months): array
    {
        $today = today();
        $monthStart = now()->startOfMonth();
        $from = Carbon::createFromFormat('Y-m-d', $months[0] . '-01')->startOfDay();

        $patients = fn ($since) => RightsCheck::where('care_provider_id', $pid)->whereNotNull('member_id')->where('ok', true)
            ->where('created_at', '>=', $since)->distinct()->count('member_id');

        $pres = Prestation::where('provider_id', $pid)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $consultActs = ActType::withTrashed()->where(fn ($q) => $q->where('category', 'like', '%consult%')->orWhere('label', 'like', 'consultation%'))->pluck('id');
        $inv = ProviderInvoice::where('provider_id', $pid)->selectRaw('status, count(*) n, sum(amount_minor) s')->groupBy('status')->get()->keyBy('status');
        $n = fn ($st) => (int) ($inv[$st]->n ?? 0);
        $s = fn ($st) => (int) ($inv[$st]->s ?? 0);

        // Même règle que le portail (« Mes factures ») : une prise en charge déjà facturée, même
        // rejetée, ne se refacture pas en ligne aujourd'hui (correction et renvoi : phase 5).
        $invoicedClaims = ProviderInvoice::where('provider_id', $pid)->whereNotNull('care_claim_id')->pluck('care_claim_id');
        $tobill = (int) Prestation::where('provider_id', $pid)->where('status', 'validated')->whereNotIn('care_claim_id', $invoicedClaims)->sum('mutual_part_minor');

        $hasDecided = Schema::hasColumn('provider_invoices', 'decided_at');
        $paidQ = fn () => ProviderInvoice::where('provider_id', $pid)->where('status', 'paid')->whereNotNull('decided_at');

        $series = [
            'care' => Prestation::where('provider_id', $pid)->where('status', '!=', 'rejected')->where('care_date', '>=', $from->toDateString())
                ->selectRaw("date_format(care_date, '%Y-%m') ym, count(*) n")->groupBy('ym')->pluck('n', 'ym')->map(fn ($v) => (int) $v)->all(),
            'invoices' => ProviderInvoice::where('provider_id', $pid)->where('created_at', '>=', $from)
                ->selectRaw("date_format(created_at, '%Y-%m') ym, count(*) n")->groupBy('ym')->pluck('n', 'ym')->map(fn ($v) => (int) $v)->all(),
            'paid' => $hasDecided ? $paidQ()->where('decided_at', '>=', $from)
                ->selectRaw("date_format(decided_at, '%Y-%m') ym, sum(amount_minor) s")->groupBy('ym')->pluck('s', 'ym')->map(fn ($v) => (int) $v)->all() : [],
        ];

        $byAct = Prestation::where('provider_id', $pid)->where('status', '!=', 'rejected')
            ->selectRaw('act_type_id, count(*) n')->groupBy('act_type_id')->pluck('n', 'act_type_id');
        $labels = ActType::withTrashed()->whereIn('id', $byAct->keys())->pluck('label', 'id');

        $delay = ['days' => 0, 'n' => 0];
        if ($hasDecided) {
            $row = $paidQ()->selectRaw('sum(timestampdiff(HOUR, created_at, decided_at)) h, count(*) n')->first();
            $delay = ['days' => ((int) ($row->h ?? 0)) / 24, 'n' => (int) ($row->n ?? 0)];
        }

        return [
            'counts' => [
                'patients_today' => $patients($today), 'patients_month' => $patients($monthStart),
                'pending' => (int) (($pres['captured'] ?? 0) + ($pres['controlled'] ?? 0)), 'validated' => (int) ($pres['validated'] ?? 0),
                'rejected' => (int) ($pres['rejected'] ?? 0),
                'consultations' => Prestation::where('provider_id', $pid)->where('status', '!=', 'rejected')->whereIn('act_type_id', $consultActs)->count(),
                'inv_total' => $inv->sum('n'), 'inv_accepted' => $n('processed') + $n('prepared') + $n('paid'),
                'inv_rejected' => $n('rejected'), 'inv_paid' => $n('paid'),
            ],
            'money' => [
                'billed' => (int) $inv->sum('s'), 'accepted' => $s('processed') + $s('prepared') + $s('paid'), 'paid' => $s('paid'),
                'due' => $s('captured') + $s('processed') + $s('prepared'), 'rejected' => $s('rejected'), 'tobill' => $tobill,
            ],
            'series' => $series,
            'careTotal' => (int) $pres->sum() - (int) ($pres['rejected'] ?? 0),
            'byAct' => $byAct->mapWithKeys(fn ($v, $id) => [($labels[$id] ?? 'Acte n° ' . $id) => (int) $v])->all(),
            'delay' => $delay,
            'alerts' => $this->tenantAlerts($pid, $hasDecided),
        ];
    }

    /** Alertes lues dans la mutuelle : convention, factures en retard, rejets, paiements reçus. */
    private function tenantAlerts(int $pid, bool $hasDecided): array
    {
        $alerts = [];
        $c = ProviderConvention::forProvider($pid);
        if ($c) {
            if (! $c->active) {
                $alerts[] = ['level' => 'danger', 'kind' => 'convention', 'text' => 'Convention suspendue par la mutuelle : vérification des droits et déclarations fermées.'];
            } elseif ($c->ends_on && $c->ends_on->lt(today())) {
                $alerts[] = ['level' => 'danger', 'kind' => 'convention', 'text' => 'Convention arrivée à échéance le ' . $c->ends_on->format('d/m/Y') . '.'];
            } elseif ($c->ends_on && $c->ends_on->lte(today()->addDays(60))) {
                $alerts[] = ['level' => 'warning', 'kind' => 'convention', 'text' => 'Convention arrivant à échéance le ' . $c->ends_on->format('d/m/Y') . '.'];
            }
            // « Facture en retard » seulement si la convention fixe un délai de paiement (jamais de délai inventé).
            $days = Schema::hasColumn('provider_conventions', 'payment_days') ? $c->payment_days : null;
            if ($days) {
                $late = ProviderInvoice::where('provider_id', $pid)->whereIn('status', ProviderInvoice::PAYABLE)->where('created_at', '<', now()->subDays($days));
                if (($cnt = (clone $late)->count()) > 0) {
                    $alerts[] = ['level' => 'warning', 'kind' => 'late', 'text' => "{$cnt} facture(s) non payée(s) au-delà du délai de paiement de la convention ({$days} jours).", 'amount' => (int) $late->sum('amount_minor')];
                }
            }
        }
        $since = now()->subDays(30);
        $rejected = Prestation::where('provider_id', $pid)->where('status', 'rejected')->where('updated_at', '>=', $since)->count();
        if ($rejected) {
            $alerts[] = ['level' => 'warning', 'kind' => 'rejected', 'text' => "{$rejected} prise(s) en charge rejetée(s) ces 30 derniers jours : le motif est visible dans « Prises en charge »."];
        }
        $invRejected = ProviderInvoice::where('provider_id', $pid)->where('status', 'rejected')->where('updated_at', '>=', $since)->count();
        if ($invRejected) {
            $alerts[] = ['level' => 'warning', 'kind' => 'rejected', 'text' => "{$invRejected} facture(s) rejetée(s) ces 30 derniers jours."];
        }
        if ($hasDecided) {
            $paid = ProviderInvoice::where('provider_id', $pid)->where('status', 'paid')->where('decided_at', '>=', now()->subDays(14));
            if (($cnt = (clone $paid)->count()) > 0) {
                $alerts[] = ['level' => 'success', 'kind' => 'paid', 'text' => "{$cnt} paiement(s) enregistré(s) par la mutuelle ces 14 derniers jours.", 'amount' => (int) $paid->sum('amount_minor')];
            }
        }

        return $alerts;
    }

    /** Alertes centrales : agrément, documents, affiliations récentes. */
    private function centralAlerts(CareProvider $provider): array
    {
        $alerts = [];
        if ($provider->isPending()) {
            $alerts[] = ['level' => 'info', 'kind' => 'pending', 'text' => 'Établissement en attente : il sera reconnu dès qu’une mutuelle aura accepté votre demande d’affiliation.'];
        }
        if ($provider->approval_expires_on) {
            if ($provider->approval_expires_on->lt(today())) {
                $alerts[] = ['level' => 'danger', 'kind' => 'approval', 'text' => 'Agrément expiré depuis le ' . $provider->approval_expires_on->format('d/m/Y') . ' : mettez à jour la fiche et le document.'];
            } elseif ($provider->approval_expires_on->lte(today()->addDays(60))) {
                $alerts[] = ['level' => 'warning', 'kind' => 'approval', 'text' => 'Agrément expirant le ' . $provider->approval_expires_on->format('d/m/Y') . '.'];
            }
        }
        foreach (ProviderDocument::where('care_provider_id', $provider->id)->whereNotNull('expires_on')->where('expires_on', '<=', today()->addDays(60))->orderBy('expires_on')->limit(5)->get() as $doc) {
            $alerts[] = ['level' => $doc->expires_on->lt(today()) ? 'danger' : 'warning', 'kind' => 'document',
                'text' => '« ' . $doc->title . ' » ' . ($doc->expires_on->lt(today()) ? 'expiré depuis le ' : 'expire le ') . $doc->expires_on->format('d/m/Y') . '.'];
        }
        foreach (ProviderAffiliation::with('mutual')->where('care_provider_id', $provider->id)->whereIn('status', ['approved', 'rejected'])
            ->where('decided_at', '>=', now()->subDays(30))->get() as $a) {
            if (! $a->mutual) {
                continue;
            }
            $alerts[] = $a->status === 'approved'
                ? ['level' => 'success', 'kind' => 'convention', 'text' => 'Nouvelle convention avec ' . $a->mutual->name . ' (' . $a->decided_at->format('d/m/Y') . ').']
                : ['level' => 'danger', 'kind' => 'affiliation', 'text' => 'Demande d’affiliation refusée par ' . $a->mutual->name . ($a->decision_note ? ' : ' . $a->decision_note : '.')];
        }

        return $alerts;
    }
}
