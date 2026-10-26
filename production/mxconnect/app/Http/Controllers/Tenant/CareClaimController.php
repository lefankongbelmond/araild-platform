<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\CareClaimRequest;
use App\Models\Central\CareProvider;
use App\Models\Central\Currency;
use App\Models\Tenant\Alert;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use Illuminate\Http\Request;

class CareClaimController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'open');

        return view('tenant.claims.index', [
            'claims'   => CareClaim::with('member')
                ->when(in_array($status, ['open', 'closed']), fn ($q) => $q->where('status', $status))
                ->withCount('prestations')
                ->orderByDesc('opened_on')->paginate(20)->withQueryString(),
            'status'   => $status,
            'currency' => $this->currency(),
        ]);
    }

    public function create(Request $request)
    {
        return view('tenant.claims.create', [
            'members' => Member::where('status', 'active')->orderBy('last_name')->get(),
        ]);
    }

    public function store(CareClaimRequest $request)
    {
        $claim = CareClaim::create($request->validated() + ['status' => 'open']);
        return redirect()->route('claims.show', $claim)->with('success', __('mxconnect.claim.opened'));
    }

    public function show(CareClaim $claim)
    {
        $claim->load(['member', 'dependent', 'prestations.actType', 'reimbursement', 'invoices']);

        // Attach open alerts + provider names (central) for display.
        $prestationIds = $claim->prestations->pluck('id')->all();
        $alerts = Alert::open()->where('object_type', Prestation::class)
            ->whereIn('object_id', $prestationIds)->get()->groupBy('object_id');
        $providers = CareProvider::on('central')
            ->whereIn('id', $claim->prestations->pluck('provider_id')->unique())
            ->pluck('name', 'id');

        return view('tenant.claims.show', [
            'claim'     => $claim,
            'alerts'    => $alerts,
            'providers' => $providers,
            'actTypes'  => \App\Models\Tenant\ActType::orderBy('label')->get(),
            'careProviders' => $this->careProviders(),
            'currency'  => $this->currency(),
        ]);
    }

    public function close(CareClaim $claim)
    {
        abort_unless(request()->user()->hasAnyRole(['controller_validator', 'mutual_admin']), 403);
        $claim->update(['status' => 'closed']);
        return back()->with('success', __('mxconnect.claim.closed'));
    }

    /**
     * Prestataires proposés à la saisie : ceux conventionnés par la mutuelle (convention
     * active) ; tant qu'aucune convention n'est enregistrée, tout le registre actif (comme avant).
     */
    private function careProviders()
    {
        $conventioned = \Illuminate\Support\Facades\Schema::hasTable('provider_conventions')
            ? \App\Models\Tenant\ProviderConvention::where('active', true)->pluck('care_provider_id') : collect();

        return CareProvider::on('central')->where('active', true)
            ->when($conventioned->isNotEmpty(), fn ($q) => $q->whereIn('id', $conventioned))
            ->orderBy('name')->get();
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
