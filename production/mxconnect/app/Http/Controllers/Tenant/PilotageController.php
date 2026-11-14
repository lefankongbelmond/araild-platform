<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\CareProvider;
use App\Models\Tenant\Antenna;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\Member;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ServiceCategory;
use App\Services\Tenant\ActivityReportService;
use App\Services\Tenant\PilotageService;
use App\Support\SimpleXlsx;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Tableau de bord de pilotage (mission « Mutuelle paramétrable », L5, 2026-11-20) : indicateurs
 * filtrables, graphiques, export Excel (.xlsx) et version imprimable (PDF par le navigateur).
 * Même droit que les rapports d'activité (règle « reports.* ») ; consommation par garantie,
 * pharmacie et filtre par adhérent seulement avec le droit de voir le détail médical.
 */
class PilotageController extends Controller
{
    use MutualContext;

    public function __construct(private PilotageService $pilotage) {}

    public function index(Request $request)
    {
        return view('tenant.reports.dashboard', $this->data($request) + ['print' => $request->boolean('imprimer')]);
    }

    public function export(Request $request)
    {
        $d = $this->data($request);
        $r = $d['r']; $c = $d['currency'];
        $m = fn (int $minor) => round($minor / (10 ** $c->minor_unit), $c->minor_unit);
        $title = 'Tableau de bord — ' . tenant('name');
        $period = $r['from']->format('d/m/Y') . ' au ' . $r['to']->format('d/m/Y');
        $sheets = [
            'Synthèse' => [
                ['Indicateur', 'Valeur', 'Unité'],
                [$title, $period, 'Devise : ' . $c->code],
                ['Filtres', $d['filterLabel'] ?: 'aucun'],
                ['Adhérents (total)', $r['members']['total'], 'personnes'], ['Bénéficiaires couverts', $r['members']['beneficiaries'], 'personnes'],
                ['Adhésions actives', $r['members']['active']], ['Adhésions suspendues', $r['members']['suspended']],
                ['Adhésions expirées ou sorties', $r['members']['expired']], ['Adhésions en attente de validation', $r['members']['pending']],
                ['Cotisations attendues', $m($r['contributions']['expected']), $c->code], ['Cotisations encaissées (période)', $m($r['contributions']['collected']), $c->code],
                ['Taux de recouvrement', $r['contributions']['rate'], '%'], ['Arriérés', $m($r['contributions']['overdue']), $c->code],
                ['Prestations facturées', $m($r['prestations']['billed']), $c->code], ['Prise en charge (part mutuelle)', $m($r['prestations']['covered']), $c->code],
                ['Tickets modérateurs', $m($r['prestations']['copay']), $c->code], ['Prestations validées', $r['prestations']['validated']],
                ['Prestations en attente', $r['prestations']['pending']], ['Prestations rejetées', $r['prestations']['rejected']],
                ['Taux de sinistralité (prise en charge / cotisations encaissées)', $r['prestations']['loss_ratio'], '%'],
            ],
            'Par formule' => array_merge([['Formule', 'Prestations', 'Montant facturé', 'Part mutuelle']],
                array_map(fn ($x) => [$x['label'], $x['count'], $m($x['total']), $m($x['mutual'])], $r['by_formula'])),
            'Par prestataire' => array_merge([['Établissement', 'Prestations', 'Part mutuelle']],
                array_map(fn ($x) => [$x['label'], $x['count'], $m($x['mutual'])], $r['by_provider'])),
            'Plafonds' => array_merge([['Plafond', 'Formule', 'Montant', 'Titulaires', 'Concernés', '≥ 80 %', 'Épuisés', 'Consommé']],
                array_map(fn ($x) => [$x['label'], $x['formula'], $m($x['amount']), $x['holder'], $x['holders'], $x['over80'], $x['full'], $m($x['consumed'])], $r['limits'])),
            'Par mois' => array_merge([['Mois', 'Cotisations encaissées', 'Part mutuelle (prestations validées)']],
                array_map(fn ($x) => [$x['label'], $m($x['collected']), $m($x['mutual'])], array_values($r['monthly']))),
        ];
        if ($d['health']) {
            $sheets['Par garantie'] = array_merge([['Garantie', 'Prestations', 'Montant facturé', 'Part mutuelle']],
                array_map(fn ($x) => [$x['label'], $x['count'], $m($x['total']), $m($x['mutual'])], $r['by_rule']));
            $sheets['Pharmacie'] = array_merge([['Indicateur', 'Valeur'], ['Délivrances', $r['pharmacy']['count']], ['Montant délivré', $m($r['pharmacy']['total'])],
                ['Non pris en charge', $m($r['pharmacy']['excluded'])], ['Part mutuelle (validée)', $m($r['pharmacy']['mutual'])], [], ['Médicament', 'Quantité', 'Montant']],
                array_map(fn ($x) => [$x['label'], $x['qty'], $m($x['amount'])], $r['pharmacy']['top']));
        }
        $name = 'tableau-de-bord-' . $r['from']->format('Ymd') . '-' . $r['to']->format('Ymd');
        if (! SimpleXlsx::available()) {
            // Extension zip absente sur le serveur : même contenu en CSV (s'ouvre dans Excel).
            $out = fopen('php://temp', 'w+');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($sheets as $sheet => $rows) {
                fputcsv($out, [$sheet], ';');
                foreach ($rows as $row) { fputcsv($out, $row, ';'); }
                fputcsv($out, [], ';');
            }
            rewind($out);

            return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$name}.csv\""])->setPrivate();
        }

        return response(SimpleXlsx::build($sheets), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$name}.xlsx\"",
        ])->setPrivate();
    }

    private function data(Request $request): array
    {
        $health = TenantAccess::can('capacite.donnees-sante');
        $periods = ActivityReportService::periods();
        $key = $request->get('periode', 'annee');
        if ($key === 'libre') {
            $this->validateOr(route('reports.dashboard'), $request, ['du' => ['required', 'date'], 'au' => ['required', 'date', 'after_or_equal:du']],
                ['au.after_or_equal' => 'La fin doit suivre le début.']);
            [$from, $to] = [Carbon::parse($request->get('du'))->startOfDay(), Carbon::parse($request->get('au'))->endOfDay()];
        } else {
            $key = array_key_exists($key, $periods) ? $key : 'annee';
            [, $from, $to] = $periods[$key];
        }
        $v = $this->validateOr(route('reports.dashboard'), $request, [
            'formule' => ['nullable', 'integer'], 'prestataire' => ['nullable', 'integer'], 'antenne' => ['nullable', 'integer'],
            'soins' => ['nullable', 'integer'], 'statut' => ['nullable', Rule::in(array_keys(PilotageService::STATUSES))],
            'adherent' => ['nullable', 'string', 'max:40'],
        ]);
        $member = $health && filled($v['adherent'] ?? null) ? Member::where('member_code', trim($v['adherent']))->first() : null;
        $f = [
            'guarantee_id' => isset($v['formule']) ? (int) $v['formule'] : null, 'provider_id' => isset($v['prestataire']) ? (int) $v['prestataire'] : null,
            'antenna_id' => isset($v['antenne']) ? (int) $v['antenne'] : null, 'category_id' => isset($v['soins']) ? (int) $v['soins'] : null,
            'status' => $v['statut'] ?? null, 'member_id' => $member?->id ?? (filled($v['adherent'] ?? null) && $health ? -1 : null),
        ];
        $guarantees = Guarantee::orderBy('name')->pluck('name', 'id');
        $providers = CareProvider::on('central')->whereIn('id', ProviderConvention::pluck('care_provider_id'))->orderBy('name')->pluck('name', 'id');
        $antennas = class_exists(Antenna::class) ? Antenna::orderBy('name')->pluck('name', 'id') : collect();
        $categories = ServiceCategory::where('active', true)->orderBy('sort')->pluck('label', 'id');
        $label = collect([
            $f['guarantee_id'] ? 'formule : ' . ($guarantees[$f['guarantee_id']] ?? '?') : null,
            $f['provider_id'] ? 'prestataire : ' . ($providers[$f['provider_id']] ?? '?') : null,
            $f['antenna_id'] ? 'antenne : ' . ($antennas[$f['antenna_id']] ?? '?') : null,
            $f['category_id'] ? 'soins : ' . ($categories[$f['category_id']] ?? '?') : null,
            $f['status'] ? 'statut : ' . PilotageService::STATUSES[$f['status']] : null,
            $f['member_id'] ? 'adhérent : ' . ($member?->member_code ?? 'inconnu') : null,
        ])->filter()->implode(' · ');

        return [
            'r' => $this->pilotage->build($from, $to, $f), 'periods' => $periods, 'periode' => $key, 'f' => $f, 'v' => $v,
            'currency' => $this->currency(), 'health' => $health, 'filterLabel' => $label,
            'guarantees' => $guarantees, 'providers' => $providers, 'antennas' => $antennas, 'categories' => $categories,
        ];
    }
}
