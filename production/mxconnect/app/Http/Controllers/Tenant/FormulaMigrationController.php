<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\FormulaMigration;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\Tenant\FormulaMigrationService;
use Illuminate\Http\Request;

/**
 * Changements de formule (Partie 5.2, 2026-11-23) : demande avec aperçu des adhérents concernés,
 * décision par une AUTRE personne, application à la date d'effet. Voir FormulaMigrationService.
 */
class FormulaMigrationController extends Controller
{
    public function __construct(private FormulaMigrationService $service) {}

    public function index()
    {
        return view('tenant.formula-migrations.index', [
            'migrations' => FormulaMigration::with(['source', 'target'])->latest('id')->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'source' => ['nullable', 'integer'], 'target' => ['nullable', 'integer'], 'date' => ['nullable', 'date'],
        ]);
        $preview = null;
        if (! empty($data['source']) && ! empty($data['target']) && ! empty($data['date'])) {
            $preview = $this->service->candidates((int) $data['source'], (int) $data['target'], $data['date']);
        }

        return view('tenant.formula-migrations.create', [
            'formulas' => Guarantee::orderBy('name')->get(),
            'data' => $data + ['source' => null, 'target' => null, 'date' => null],
            'preview' => $preview,
            'currency' => $this->currency(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_guarantee_id' => ['required', 'integer', 'exists:guarantees,id'],
            'target_guarantee_id' => ['required', 'integer', 'exists:guarantees,id'],
            'effective_date' => ['required', 'date'],
            'keep_seniority' => ['nullable', 'boolean'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['member_ids.required' => 'Cochez au moins un adhérent.', 'note.required' => 'Indiquez le motif du changement.']);
        $data['member_ids'] = array_values(array_unique(array_map('intval', $data['member_ids'])));
        try {
            $mig = $this->service->request($data, $request->user());
        } catch (AccountingRefusal $e) {
            return back()->withInput()->withErrors(['note' => $e->getMessage()]);
        }

        return redirect()->route('formula-migrations.show', $mig)->with('success', "Demande {$mig->reference} enregistrée : une autre personne doit la valider.");
    }

    public function show(FormulaMigration $migration)
    {
        $migration->load(['source', 'target']);
        $preview = $migration->status === 'requested' || $migration->status === 'approved'
            ? $this->service->candidates($migration->source_guarantee_id, $migration->target_guarantee_id, $migration->effective_date->toDateString(), $migration->member_ids)
            : null;
        $people = User::whereIn('id', array_filter([$migration->requested_by, $migration->decided_by]))->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);

        return view('tenant.formula-migrations.show', [
            'm' => $migration, 'preview' => $preview, 'people' => $people, 'currency' => $this->currency(),
        ]);
    }

    public function approve(Request $request, FormulaMigration $migration)
    {
        return $this->act(fn () => $this->service->decide($migration, true, $request->input('note'), $request->user()),
            fn ($m) => $m->status === 'applied' ? 'Changement validé et appliqué.' : 'Changement validé : il sera appliqué le ' . $m->effective_date->format('d/m/Y') . '.');
    }

    public function reject(Request $request, FormulaMigration $migration)
    {
        return $this->act(fn () => $this->service->decide($migration, false, $request->input('note'), $request->user()), fn () => 'Demande refusée.');
    }

    public function cancel(Request $request, FormulaMigration $migration)
    {
        abort_unless((int) $migration->requested_by === (int) $request->user()->id || $request->user()->hasRole('mutual_admin'), 403);

        return $this->act(fn () => $this->service->cancel($migration, $request->user()) ?? $migration, fn () => 'Demande retirée.');
    }

    public function apply(FormulaMigration $migration)
    {
        return $this->act(fn () => $this->service->apply($migration), fn () => 'Changement de formule appliqué.');
    }

    private function act(callable $do, callable $message)
    {
        try {
            $m = $do();
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message($m));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id')) ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
