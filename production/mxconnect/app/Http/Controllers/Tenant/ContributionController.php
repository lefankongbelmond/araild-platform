<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\CollectionCancellation;
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
            'cancellations' => $this->hasCancellations()
                ? CollectionCancellation::with(['member', 'requestedBy'])->where('status', 'pending')->orderBy('id')->get() : collect(),
            'canDecide'  => TenantAccess::can('contributions.cancel.approve'),
        ]);
    }

    /** Relevé de cotisations d'un adhérent + formulaire d'encaissement. */
    public function member(Member $member)
    {
        $this->syncOverdue();
        $schedules = ContributionSchedule::whereHas('subscription', fn ($q) => $q->where('member_id', $member->id))
            ->with('subscription.guaranteeVersion.guarantee')->withSum('payments', 'amount_minor')
            ->orderBy('due_date')->orderBy('id')->get();
        // Encaissements annulés compris (barrés à l'écran, exclus des totaux).
        $payments = ContributionPayment::withTrashed()->whereIn('schedule_id', $schedules->pluck('id'))->with(['schedule', 'recordedBy'])
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
                'paid'        => (int) $payments->whereNull('deleted_at')->sum('amount_minor'),
                'arrears'     => $schedules->filter(fn ($s) => $s->status !== 'paid' && $s->due_date->lt($today))->sum($remaining),
                'upcoming'    => $schedules->filter(fn ($s) => $s->status !== 'paid' && $s->due_date->gte($today))->sum($remaining),
            ],
            'open'       => $schedules->filter(fn ($s) => $s->status !== 'paid')->sum($remaining),
            'currency'   => $this->currency(),
            'canCollect' => TenantAccess::can('contributions.collect'),
            'canCancel'  => TenantAccess::can('contributions.cancel.request'),
            'canDecide'  => TenantAccess::can('contributions.cancel.approve'),
            'cancellations' => $this->hasCancellations()
                ? CollectionCancellation::with(['requestedBy', 'decidedBy'])->where('member_id', $member->id)->get()->keyBy(fn ($c) => $c->collection_ref . '#' . $c->id) : collect(),
            // Droit d'adhésion prévu par la garantie mais pas encore appelé (souscriptions antérieures au 2026-11-07).
            'feeToCall'  => TenantAccess::can('contributions.fee') ? $this->feeToCall($member) : 0,
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
        ], ['reference.required_if' => __('Indiquez la référence (n° de transaction ou de bordereau).')]);

        try {
            $r = $this->collections->collect($member, $this->currency()->toMinor($this->amount($data['amount'])), $data['paid_on'],
                $data['mode'], isset($data['reference']) ? trim($data['reference']) : null, (int) $request->user()->id);
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('contributions.member', $member)->with('success', __('Encaissement :ref enregistré : :amount sur :count échéance(s). Reçu disponible ; mouvement de trésorerie créé.',
            ['ref' => $r['ref'], 'amount' => $this->currency()->format($r['amount']), 'count' => $r['payments']->count()]));
    }

    /** Raccourci de la liste : solde d'une échéance (montant restant, aujourd'hui). */
    public function recordPayment(Request $request, ContributionSchedule $schedule)
    {
        $request->validate(['mode' => ['required', 'in:cash,group']]);
        $member = $schedule->subscription?->member;
        abort_unless($member, 422, __('Échéance sans adhérent.'));
        try {
            $r = $this->collections->collect($member, $schedule->remainingMinor(), now()->toDateString(), $request->get('mode'), null,
                (int) $request->user()->id, [$schedule->id]);
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Échéance :label encaissée (:ref). Reçu généré ; mouvement de trésorerie créé.', ['label' => $schedule->label(), 'ref' => $r['ref']]));
    }

    /** Demande d'annulation d'un encaissement (trésorier, motif) : décidée par une autre personne. */
    public function requestCancellation(Request $request, string $ref)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']],
            ['reason.required' => __('Indiquez le motif de l’annulation.'), 'reason.min' => __('Précisez le motif de l’annulation.')]);
        try {
            $c = $this->collections->requestCancellation($ref, trim($data['reason']), (int) $request->user()->id);
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Annulation de l’encaissement :ref demandée. Elle attend la décision de l’administrateur ou du contrôleur.', ['ref' => $c->collection_ref]));
    }

    public function approveCancellation(Request $request, CollectionCancellation $cancellation)
    {
        try {
            $this->collections->approveCancellation($cancellation, (int) $request->user()->id);
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Encaissement :ref annulé : échéances rouvertes, mouvement de trésorerie inverse créé, reçu marqué « ANNULÉ ».', ['ref' => $cancellation->collection_ref]));
    }

    public function rejectCancellation(Request $request, CollectionCancellation $cancellation)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']],
            ['note.required' => __('Indiquez le motif du refus.'), 'note.min' => __('Précisez le motif du refus.')]);
        try {
            $this->collections->rejectCancellation($cancellation, (int) $request->user()->id, trim($data['note']));
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Demande d’annulation de :ref refusée : l’encaissement est maintenu.', ['ref' => $cancellation->collection_ref]));
    }

    /** Appelle le droit d'adhésion prévu par la garantie (souscription validée avant le 2026-11-07). */
    public function callFee(Member $member, ScheduleGenerationService $schedules)
    {
        $n = 0;
        foreach ($member->subscriptions()->where('status', 'validated')->with('guaranteeVersion')->get() as $sub) {
            $n += $schedules->fee($sub, now()->startOfDay()) ? 1 : 0;
        }

        return back()->with($n ? 'success' : 'error', $n ? 'Droit d’adhésion appelé : il figure dans l’échéancier et peut être encaissé.' : 'Aucun droit d’adhésion à appeler pour cet adhérent.');
    }

    /** Règle de suspension : délai de grâce réglable par la mutuelle (2026-11-24). */
    public function policy()
    {
        return view('tenant.contributions.policy', [
            'grace' => \App\Models\Tenant\MutualSetting::int(\App\Support\ArrearsPolicy::KEY),
            'max' => \App\Support\ArrearsPolicy::MAX_DAYS,
        ]);
    }

    public function updatePolicy(Request $request)
    {
        $d = $request->validate(['grace_days' => ['nullable', 'integer', 'between:0,' . \App\Support\ArrearsPolicy::MAX_DAYS]],
            ['grace_days.between' => __('Le délai de grâce doit être compris entre 0 et :max jours.', ['max' => \App\Support\ArrearsPolicy::MAX_DAYS])]);
        $days = (int) ($d['grace_days'] ?? 0);
        \App\Models\Tenant\MutualSetting::put(\App\Support\ArrearsPolicy::KEY, $days > 0 ? (string) $days : null, $request->user()->id);

        return back()->with('success', $days > 0
            ? "Délai de grâce enregistré : les droits sont suspendus {$days} jour(s) après une échéance impayée."
            : __('Délai de grâce retiré : les droits sont suspendus dès le lendemain d’une échéance impayée.'));
    }

    /** Prolonge les échéanciers (horizon glissant) et marque les retards. */
    public function refresh(ScheduleGenerationService $schedules)
    {
        $r = $schedules->refreshAll();

        return back()->with('success', __('Échéanciers actualisés : :created échéance(s) ajoutée(s), :overdue passée(s) en retard.', ['created' => $r['created'], 'overdue' => $r['overdue']]));
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
                    fputcsv($out, [$mem?->member_code, $mem?->last_name, $mem?->first_name, $mem?->phone, $s->label(), $s->due_date?->format('d/m/Y'),
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

        $html = Storage::disk('local')->get($payment->receipt_path);
        if ($payment->trashed()) {
            $c = $this->hasCancellations() ? CollectionCancellation::where('collection_ref', $payment->collection_ref)->where('status', 'approved')->latest('id')->first() : null;
            $banner = '<div style="border:2px solid #C0392B;color:#C0392B;padding:.6rem 1rem;margin:1rem auto;max-width:640px;font:600 1rem system-ui,sans-serif">ANNULÉ'
                . ($c ? ' le ' . e($c->decided_at?->format('d/m/Y')) . ' — motif : ' . e($c->reason) : '') . '. Ce reçu n’a plus de valeur.</div>';
            $html = preg_replace('/<body([^>]*)>/', '<body$1>' . $banner, $html, 1);
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }

    private function hasCancellations(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('collection_cancellations');
    }

    /** Montant du droit d'adhésion encore à appeler pour un adhérent (0 si rien). */
    private function feeToCall(Member $member): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('contribution_schedules', 'kind')) {
            return 0;
        }

        return (int) $member->subscriptions()->where('status', 'validated')->with('guaranteeVersion')->get()
            ->filter(fn ($s) => ! $s->schedules()->where('kind', 'membership_fee')->exists())
            ->sum(fn ($s) => (int) ($s->guaranteeVersion?->membership_fee_minor ?? 0));
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
