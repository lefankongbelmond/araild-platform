<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Member;
use App\Services\AccountingRefusal;
use App\Services\ScheduleGenerationService;
use App\Services\Tenant\ContributionCollectionService;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Cotisations (refait le 2026-11-06) : échéances par état avec recherche et totaux, relevé
 * par adhérent, encaissement au guichet (partiel ou sur plusieurs échéances, un reçu, un
 * mouvement de trésorerie), export CSV pour les relances, actualisation des échéanciers.
 */
class ContributionController extends Controller
{
    use MutualContext;

    private const TABS = ['overdue', 'to_pay', 'paid'];

    public function __construct(private ContributionCollectionService $collections) {}

    public function index(Request $request)
    {
        $this->syncOverdue();
        $status = in_array($request->get('status'), self::TABS, true) ? $request->get('status') : 'overdue';

        $totals = [];
        foreach (self::TABS as $tab) {
            $q = ContributionSchedule::where('status', $tab);
            $paid = (int) ContributionPayment::whereIn('schedule_id', (clone $q)->select('id'))->sum('amount_minor');
            $totals[$tab] = ['count' => (clone $q)->count(), 'due' => (int) (clone $q)->sum('due_minor'), 'paid' => $paid];
        }

        return view('tenant.contributions.index', [
            'schedules'  => $this->query($request, $status)->with('subscription.member')->withSum('payments', 'amount_minor')
                ->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString(),
            'status'     => $status,
            'totals'     => $totals,
            'currency'   => $this->currency(),
            'canCollect' => TenantAccess::can('contributions.collect'),
            'canRefresh' => TenantAccess::can('contributions.refresh'),
        ]);
    }

    /** Relevé de cotisations d'un adhérent + formulaire d'encaissement. */
    public function member(Member $member)
    {
        $this->syncOverdue();
        $schedules = ContributionSchedule::whereHas('subscription', fn ($q) => $q->where('member_id', $member->id))
            ->with('subscription.guaranteeVersion.guarantee')->withSum('payments', 'amount_minor')
            ->orderBy('due_date')->orderBy('id')->get();
        $payments = ContributionPayment::whereIn('schedule_id', $schedules->pluck('id'))->with(['schedule', 'recordedBy'])
            ->orderByDesc('paid_on')->orderByDesc('id')->get();
        $today = now()->startOfDay();
        $remaining = fn ($s) => max(0, $s->due_minor - (int) $s->payments_sum_amount_minor);

        return view('tenant.contributions.member', [
            'member'     => $member,
            'schedules'  => $schedules,
            // Un encaissement = plusieurs lignes de paiement de même référence : regroupées.
            'collections' => $payments->groupBy(fn ($p) => $p->collection_ref ?: 'P' . $p->id),
            'summary'    => [
                'due_to_date' => $schedules->filter(fn ($s) => $s->due_date->lte($today))->sum('due_minor'),
                'paid'        => (int) $payments->sum('amount_minor'),
                'arrears'     => $schedules->filter(fn ($s) => $s->status !== 'paid' && $s->due_date->lt($today))->sum($remaining),
                'upcoming'    => $schedules->filter(fn ($s) => $s->status !== 'paid' && $s->due_date->gte($today))->sum($remaining),
            ],
            'open'       => $schedules->filter(fn ($s) => $s->status !== 'paid')->sum($remaining),
            'currency'   => $this->currency(),
            'canCollect' => TenantAccess::can('contributions.collect'),
        ]);
    }

    /** Encaissement au guichet : réparti sur les échéances, la plus ancienne d'abord. */
    public function collect(Request $request, Member $member)
    {
        $data = $request->validate([
            'amount'    => ['required', 'string', 'max:24'],
            'paid_on'   => ['required', 'date'],
            'mode'      => ['required', 'in:' . implode(',', array_keys(ContributionPayment::MODES))],
            'reference' => ['nullable', 'string', 'max:60', 'required_if:mode,bank,mobile_money'],
        ], ['reference.required_if' => 'Indiquez la référence (n° de transaction ou de bordereau).']);

        try {
            $r = $this->collections->collect($member, $this->currency()->toMinor($this->amount($data['amount'])), $data['paid_on'],
                $data['mode'], isset($data['reference']) ? trim($data['reference']) : null, (int) $request->user()->id);
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('contributions.member', $member)->with('success', 'Encaissement ' . $r['ref'] . ' enregistré : '
            . $this->currency()->format($r['amount']) . ' sur ' . $r['payments']->count() . ' échéance(s). Reçu disponible ; mouvement de trésorerie créé.');
    }

    /** Raccourci de la liste : solde d'une échéance (montant restant, aujourd'hui). */
    public function recordPayment(Request $request, ContributionSchedule $schedule)
    {
        $request->validate(['mode' => ['required', 'in:cash,group']]);
        $member = $schedule->subscription?->member;
        abort_unless($member, 422, 'Échéance sans adhérent.');
        try {
            $r = $this->collections->collect($member, $schedule->remainingMinor(), now()->toDateString(), $request->get('mode'), null,
                (int) $request->user()->id, [$schedule->id]);
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Échéance ' . $schedule->period . ' encaissée (' . $r['ref'] . '). Reçu généré ; mouvement de trésorerie créé.');
    }

    /** Prolonge les échéanciers (horizon glissant) et marque les retards. */
    public function refresh(ScheduleGenerationService $schedules)
    {
        $r = $schedules->refreshAll();

        return back()->with('success', "Échéanciers actualisés : {$r['created']} échéance(s) ajoutée(s), {$r['overdue']} passée(s) en retard.");
    }

    /** Export CSV de l'onglet courant (relances : téléphone, reste dû). */
    public function export(Request $request)
    {
        $this->syncOverdue();
        $status = in_array($request->get('status'), self::TABS, true) ? $request->get('status') : 'overdue';
        $c = $this->currency();
        $m = fn (int $minor) => number_format($minor / (10 ** $c->minor_unit), $c->minor_unit, ',', '');
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code adhérent', 'Nom', 'Prénom', 'Téléphone', 'Période', 'Échéance', 'Montant dû', 'Déjà payé', 'Reste à payer', 'Devise', 'État'], ';');
        $this->query($request, $status)->with('subscription.member')->withSum('payments', 'amount_minor')->orderBy('due_date')
            ->chunk(500, function ($rows) use ($out, $m, $c) {
                foreach ($rows as $s) {
                    $mem = $s->subscription?->member;
                    $paid = (int) $s->payments_sum_amount_minor;
                    fputcsv($out, [$mem?->member_code, $mem?->last_name, $mem?->first_name, $mem?->phone, $s->period, $s->due_date?->format('d/m/Y'),
                        $m($s->due_minor), $m($paid), $m(max(0, $s->due_minor - $paid)), $c->code, __('mxconnect.contribution.status_' . $s->status)], ';');
                }
            });
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cotisations-' . $status . '-' . now()->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    public function receipt(ContributionPayment $payment)
    {
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return response(Storage::disk('local')->get($payment->receipt_path))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }

    private function query(Request $request, string $status)
    {
        $term = trim((string) $request->get('q'));

        return ContributionSchedule::query()->where('status', $status)
            ->when($term !== '', fn ($q) => $q->whereHas('subscription.member', fn ($w) => $w->where(fn ($x) => $x
                ->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('member_code', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))));
    }

    /** Échéances dépassées marquées « en retard » sans attendre la tâche de nuit (cron absent ou en retard). */
    private function syncOverdue(): void
    {
        ContributionSchedule::where('status', 'to_pay')->whereDate('due_date', '<', now()->toDateString())->update(['status' => 'overdue']);
    }
}
