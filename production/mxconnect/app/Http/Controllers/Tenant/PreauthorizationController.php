<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Central\Currency;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Member;
use App\Models\Tenant\Preauthorization;
use App\Models\Tenant\ProviderConvention;
use App\Services\AccountingRefusal;
use App\Services\Tenant\PreauthorizationService;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accords préalables côté mutuelle (2026-11-19) : file d'examen, détail (contrôles automatiques,
 * informations médicales réservées aux personnes habilitées), décision, demande saisie par la mutuelle.
 */
class PreauthorizationController extends Controller
{
    public function __construct(private PreauthorizationService $service) {}

    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->get('statut'), Preauthorization::STATUSES) ? $request->get('statut') : null;
        $review = array_key_exists((string) $request->get('examen'), Preauthorization::REVIEWS) ? $request->get('examen') : null;

        return view('tenant.preauth.index', [
            'items' => Preauthorization::with(['member', 'dependent', 'actType'])
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->orderByRaw("status = 'requested' desc"))
                ->when($review, fn ($q) => $q->where('review_type', $review))
                ->when($request->boolean('urgence'), fn ($q) => $q->where('emergency', true))
                ->latest('id')->paginate(25)->withQueryString(),
            'counts' => Preauthorization::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status, 'review' => $review, 'currency' => $this->currency(),
            'providers' => $this->providerNames(),
            'conventions' => ProviderConvention::where('active', true)->pluck('care_provider_id'),
            'acts' => ActType::orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function show(Preauthorization $preauth)
    {
        \App\Support\HealthAccessLog::record('preauthorization', $preauth->id);
        $preauth->load(['member', 'dependent', 'actType', 'rule', 'prestation']);

        return view('tenant.preauth.show', [
            'p' => $preauth, 'currency' => $this->currency(), 'providers' => $this->providerNames(),
            'users' => \App\Models\Tenant\User::get(['id', 'first_name', 'last_name'])->keyBy('id'),
            'health' => TenantAccess::can('capacite.donnees-sante'),
        ]);
    }

    /** Demande saisie par la mutuelle (au nom d'un établissement conventionné). */
    public function store(Request $request)
    {
        $d = $request->validate([
            'member_code'     => ['required', 'string', 'max:40'],
            'dependent_id'    => ['nullable', 'integer'],
            'provider_id'     => ['required', 'integer', Rule::in(ProviderConvention::where('active', true)->pluck('care_provider_id')->all())],
            'act_type_id'     => ['required', 'integer', Rule::exists('act_types', 'id')],
            'planned_on'      => ['required', 'date', 'after_or_equal:today'],
            'estimated'       => ['required', 'numeric', 'min:1', 'max:999999999'],
            'clinical_info'   => ['nullable', 'string', 'max:2000'],
        ], ['provider_id.in' => __('Choisissez un établissement conventionné.'), 'planned_on.after_or_equal' => __('La date prévue ne peut pas être passée.')]);
        $member = Member::where('member_code', trim($d['member_code']))->first();
        if (! $member) {
            return back()->withInput()->withErrors(['member_code' => __('Aucun adhérent avec ce numéro.')]);
        }
        $dependent = ! empty($d['dependent_id']) ? $member->dependents()->whereKey($d['dependent_id'])->first() : null;
        if (! empty($d['dependent_id']) && ! $dependent) {
            return back()->withInput()->withErrors(['dependent_id' => __('Cet ayant droit n’appartient pas à l’adhérent.')]);
        }
        $p = $this->service->request([
            'member' => $member, 'dependent' => $dependent, 'provider_id' => (int) $d['provider_id'], 'act_type_id' => (int) $d['act_type_id'],
            'planned_on' => $d['planned_on'], 'estimated_minor' => $this->currency()->toMinor((float) $d['estimated']),
            'clinical_info' => TenantAccess::can('capacite.donnees-sante') ? ($d['clinical_info'] ?? null) : null,
        ], $request->user(), 'mutual');

        return redirect()->route('preauth.show', $p)->with('success', __('Demande d’accord préalable enregistrée. Une autre personne doit l’examiner.'));
    }

    public function decide(Request $request, Preauthorization $preauth)
    {
        $d = $request->validate([
            'decision'    => ['required', Rule::in(['approved', 'rejected'])],
            'note'        => ['nullable', 'string', 'max:1000'],
            'approved'    => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        try {
            $this->service->decide($preauth, $request->user(), $d['decision'], $d['note'] ?? null,
                ($d['approved'] ?? null) === null || $d['approved'] === '' ? null : $this->currency()->toMinor((float) $d['approved']),
                $d['valid_until'] ?? null);
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $preauth->refresh();

        return back()->with('success', $d['decision'] === 'approved'
            ? "Accord préalable n° {$preauth->number} accordé. Le prestataire le voit dans son portail."
            : __('Demande rejetée. Le motif est communiqué au prestataire.'));
    }

    private function providerNames()
    {
        $ids = Preauthorization::distinct()->pluck('provider_id')->merge(ProviderConvention::pluck('care_provider_id'))->unique();

        return CareProvider::on('central')->whereIn('id', $ids)->pluck('name', 'id');
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
