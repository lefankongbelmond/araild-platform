<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\Medicine;
use App\Services\GuaranteeVersioningService;
use App\Support\ReferenceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Import des listes de référence (actes, médicaments, formules de garanties).
 * La mutuelle choisit les éléments ; un code déjà présent n'est jamais écrasé.
 */
class CatalogImportController extends Controller
{
    use MutualContext;

    public const KINDS = ['actes' => 'Actes de soins', 'medicaments' => 'Médicaments essentiels', 'garanties' => 'Formules de garanties'];

    public function show(Request $request, string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        abort_unless($request->user()?->can('manage-parameters'), 403);

        [$items, $existing] = match ($kind) {
            'actes'       => [ReferenceCatalog::ACTS, ActType::withTrashed()->pluck('code')->all()],
            'medicaments' => [ReferenceCatalog::MEDICINES, Medicine::withTrashed()->pluck('code')->all()],
            'garanties'   => [ReferenceCatalog::GUARANTEES, Guarantee::withTrashed()->pluck('code')->all()],
        };

        return view('tenant.parameters.import', [
            'kind' => $kind, 'kinds' => self::KINDS, 'items' => $items,
            'existing' => array_flip($existing), 'currency' => $this->currency(),
        ]);
    }

    public function store(Request $request, string $kind, GuaranteeVersioningService $versioning)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        abort_unless($request->user()?->can('manage-parameters'), 403);
        $codes = array_values(array_filter((array) $request->input('codes', []), 'is_string'));
        if (! $codes) {
            return back()->with('error', 'Cochez au moins un élément à importer.');
        }
        $currency = $this->currency();
        $added = 0;

        DB::transaction(function () use ($kind, $codes, $request, $versioning, $currency, &$added) {
            if ($kind === 'actes') {
                $have = array_flip(ActType::withTrashed()->pluck('code')->all());
                foreach ($codes as $code) {
                    if (isset(ReferenceCatalog::ACTS[$code]) && ! isset($have[$code])) {
                        [$label, $cat] = ReferenceCatalog::ACTS[$code];
                        ActType::create(['code' => $code, 'label' => $label, 'category' => $cat]);
                        $added++;
                    }
                }
            } elseif ($kind === 'medicaments') {
                $have = array_flip(Medicine::withTrashed()->pluck('code')->all());
                foreach ($codes as $code) {
                    if (isset(ReferenceCatalog::MEDICINES[$code]) && ! isset($have[$code])) {
                        [$label, $pres, $cat] = ReferenceCatalog::MEDICINES[$code];
                        Medicine::create(['code' => $code, 'label' => $label, 'presentation' => $pres, 'category' => $cat,
                            'reference_price_minor' => 0, 'currency_code' => $currency->code]);
                        $added++;
                    }
                }
            } else {
                $have = array_flip(Guarantee::withTrashed()->pluck('code')->all());
                foreach ($codes as $code) {
                    if (! isset(ReferenceCatalog::GUARANTEES[$code]) || isset($have[$code])) {
                        continue;
                    }
                    [$name, $desc, , , , ] = ReferenceCatalog::GUARANTEES[$code];
                    $p = (array) $request->input("g.$code", []);
                    $coverage = max(0, min(100, (float) ($p['coverage'] ?? 0)));
                    $contribution = $this->amount($p['contribution'] ?? 0);
                    if ($contribution <= 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'g' => "« {$name} » : saisissez le montant de la cotisation avant l’import.",
                        ]);
                    }
                    $versioning->createWithFirstVersion(
                        guarantee: ['code' => $code, 'name' => $name, 'description' => $desc, 'status' => 'active'],
                        version: [
                            'base_contribution_minor' => $currency->toMinor($contribution),
                            'periodicity'             => in_array($p['periodicity'] ?? '', ['monthly', 'quarterly', 'annual'], true) ? $p['periodicity'] : 'monthly',
                            'coverage_rate'           => $coverage,
                            'copay_rate'              => 100 - $coverage,
                            'membership_fee_minor'    => $currency->toMinor($this->amount($p['fee'] ?? 0)),
                            'observation_days'        => max(0, min(730, (int) ($p['observation'] ?? 0))),
                            'valid_from'              => now()->toDateString(),
                        ],
                    );
                    $added++;
                }
            }
        });

        $target = ['actes' => 'act-types.index', 'medicaments' => 'medicines.index', 'garanties' => 'guarantees.index'][$kind];
        $note = $kind === 'medicaments' && $added ? ' Renseignez maintenant les prix de référence (affichés « à renseigner »).' : '';

        return redirect()->route($target)->with('success', $added . ' élément(s) importé(s) depuis la liste de référence.' . $note);
    }
}
