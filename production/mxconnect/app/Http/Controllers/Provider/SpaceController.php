<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Provider\ProviderAffiliation;
use App\Models\Tenant\ActType;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\ProviderConvention;
use App\Services\Provider\ProviderDashboardService;
use App\Services\Provider\ProviderSpaceService;
use App\Support\ProviderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Espace prestataire central : tableau de bord, mutuelles et conventions, entrée dans le
 * portail d'une mutuelle, demandes d'affiliation. Établissement en cours = middleware
 * ProviderSpaceGuard (ProviderAccess::current()).
 */
class SpaceController extends Controller
{
    public function __construct(private ProviderSpaceService $space) {}

    public function dashboard(ProviderDashboardService $dash)
    {
        $m = ProviderAccess::current();

        return view('provider.dashboard', [
            'provider' => $m->provider, 'd' => $dash->build($m->provider),
            'showMoney' => ProviderAccess::allowed('finance.view'),
            'requests' => ProviderAffiliation::with('mutual')->where('care_provider_id', $m->care_provider_id)->where('status', 'requested')->get(),
        ]);
    }

    /** Changer d'établissement (personne rattachée à plusieurs établissements). */
    public function switch(Request $request)
    {
        $id = (int) $request->input('membership');
        $ok = $request->user('provider')->activeMemberships()->whereKey($id)->exists();
        abort_unless($ok, 403);
        $request->session()->put('provider_membership', $id);

        return redirect()->route('pro.dashboard');
    }

    /** Choisir la mutuelle avant d'ouvrir son portail (vérifier les droits, prises en charge, factures). */
    public function go(string $target)
    {
        [, $perm] = ProviderSpaceService::TARGETS[$target];
        abort_unless(ProviderAccess::allowed($perm), 403, 'Votre rôle dans l’établissement ne permet pas cette action.');
        $mutuals = $this->space->conventionedMutuals(ProviderAccess::current()->provider);

        return view('provider.go', ['target' => $target, 'mutuals' => $mutuals]);
    }

    public function open(Request $request, string $target, string $mutual)
    {
        $m = ProviderAccess::current();
        $mut = $this->space->conventionedMutuals($m->provider)->firstWhere('id', $mutual);
        abort_unless($mut, 404);
        $url = $this->space->handoffUrl($request->user('provider'), $m, $mut, $target, $request, $why);
        if (! $url) {
            return back()->with('error', $why);
        }

        return redirect()->away($url);
    }

    /** Mes mutuelles : conventions en place, demandes, mutuelles disponibles. */
    public function mutuals()
    {
        $m = ProviderAccess::current();

        return view('provider.mutuals', [
            'provider' => $m->provider,
            'affiliations' => $this->space->affiliations($m->provider),
            'requestable' => ProviderAccess::allowed('affiliation.request') ? $this->space->requestableMutuals($m->provider) : collect(),
        ]);
    }

    /** Convention et tarifs avec une mutuelle (lus dans la base de la mutuelle). */
    public function mutual(string $mutual)
    {
        $m = ProviderAccess::current();
        $aff = ProviderAffiliation::where('care_provider_id', $m->care_provider_id)->where('mutual_id', $mutual)->whereIn('status', ['approved', 'suspended'])->first();
        $mut = $aff ? Mutual::find($mutual) : null;
        abort_unless($mut, 404);
        $pid = $m->care_provider_id;
        $data = $mut->run(function () use ($pid) {
            $c = ProviderConvention::forProvider($pid);
            $hasTariff = Schema::hasColumn('act_types', 'tariff_minor');

            return [
                // Les notes internes de la mutuelle et son historique ne sont jamais montrés à l'établissement.
                'convention' => $c ? collect($c->toArray())->only(['reference', 'signed_on', 'starts_on', 'ends_on', 'active', 'contact_name',
                    'contact_phone', 'transmission_days', 'payment_days', 'billing_terms'])->all() : null,
                'inForce' => (bool) $c?->inForce(),
                'acts' => ActType::orderBy('category')->orderBy('label')->get(['code', 'label', 'category', ...($hasTariff ? ['tariff_minor'] : [])])->toArray(),
                'hasTariff' => $hasTariff,
                // Garanties dont la version en cours accepte cet établissement (réseau vide = ouvert à tous).
                'guarantees' => GuaranteeVersion::with('guarantee')->whereNull('valid_to')->get()
                    ->filter(fn ($v) => $v->guarantee && (! is_array($v->provider_network) || in_array($pid, $v->provider_network, true)))
                    ->map(fn ($v) => ['name' => $v->guarantee->name, 'rate' => $v->coverage_rate, 'copay' => $v->copay_rate, 'open' => ! is_array($v->provider_network)])
                    ->values()->all(),
            ];
        });

        return view('provider.mutual', [
            'mutual' => $mut, 'aff' => $aff, 'currency' => Currency::on('central')->find($mut->currency_id), 'data' => $data,
            'docs' => $m->provider->documents()->where('mutual_id', $mut->id)->latest('id')->get(),
        ]);
    }

    public function requestAffiliation(Request $request)
    {
        $data = $request->validate(['mutual_id' => ['required', 'string', 'max:64'], 'message' => ['nullable', 'string', 'max:1000']],
            ['mutual_id.required' => 'Choisissez une mutuelle.']);
        $m = ProviderAccess::current();
        $aff = $this->space->requestAffiliation($m->provider, $data['mutual_id'], $request->user('provider'), $data['message'] ?? null);
        if (! $aff) {
            return back()->with('error', 'Cette mutuelle n’est pas disponible pour une demande (déjà conventionnée, demande en cours, ou autre pays).');
        }

        return back()->with('success', 'Demande d’affiliation envoyée à ' . ($aff->mutual?->name ?? 'la mutuelle') . '. Elle sera examinée par la mutuelle.');
    }

    public function withdraw(Request $request, int $affiliation)
    {
        $m = ProviderAccess::current();
        $aff = ProviderAffiliation::where('care_provider_id', $m->care_provider_id)->where('status', 'requested')->findOrFail($affiliation);
        $aff->status = 'withdrawn';
        $aff->log('Demande retirée par l’établissement', $request->user('provider')->name);
        $aff->save();

        return back()->with('success', 'Demande retirée.');
    }
}
