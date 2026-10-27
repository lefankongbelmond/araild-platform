<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Services\Tenant\ActivityReportService;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rapports d'activité (2026-11-03) : une période (mois, trimestre, année, ou dates libres),
 * des chiffres réels, une version imprimable (rapport au conseil d'administration, à l'AG
 * ou aux autorités) et un export CSV pour le tableur.
 */
class ReportController extends Controller
{
    use MutualContext;

    public function __construct(private ActivityReportService $reports) {}

    public function index(Request $request)
    {
        return view('tenant.reports.index', $this->data($request) + ['print' => $request->boolean('imprimer')]);
    }

    public function export(Request $request)
    {
        $d = $this->data($request);
        $r = $d['report']; $c = $d['currency'];
        $m = fn (int $minor) => number_format($minor / (10 ** $c->minor_unit), $c->minor_unit, ',', '');
        $rows = [
            ['Rapport d’activité', tenant('name')], ['Période', $r['from']->format('d/m/Y') . ' au ' . $r['to']->format('d/m/Y')], ['Devise', $c->code], [],
            ['Adhérents actifs', $r['members']['active']], ['Nouveaux adhérents', $r['members']['new']], ['Personnes à charge actives', $r['members']['dependents']],
            ['Adhérents suspendus', $r['members']['suspended']], ['Souscriptions validées', $r['subscriptions']['validated']], ['Souscriptions en attente', $r['subscriptions']['pending']], [],
            ['Cotisations appelées', $m($r['contributions']['called'])], ['dont encaissées', $m($r['contributions']['collected_on_called'])],
            ['Taux de recouvrement (%)', $r['contributions']['rate'] ?? '—'], ['Encaissements de la période', $m($r['contributions']['collected'])],
            ['Arriérés (échéances en retard)', $m($r['contributions']['overdue'])], [],
            ['Prestations validées', $r['prestations']['validated']], ['Montant total des soins', $m($r['prestations']['total'])],
            ['Part de la mutuelle', $m($r['prestations']['mutual'])], ['Part des adhérents', $m($r['prestations']['beneficiary'])],
            ['Prestations en attente', $r['prestations']['pending']], ['Prestations rejetées', $r['prestations']['rejected']],
            ['Ratio prestations / cotisations encaissées (%)', $r['prestations']['claims_ratio'] ?? '—'], [],
            ['Factures prestataires payées', $m($r['invoices']['paid'])], ['Factures prestataires à payer', $m($r['invoices']['payable'])],
            ['Trésorerie : entrées', $m($r['treasury']['in'])], ['Trésorerie : sorties', $m($r['treasury']['out'])], [],
            ['Par prestataire', 'Prestations', 'Part mutuelle'],
        ];
        foreach ($r['by_provider'] as $p) { $rows[] = [$p['label'], $p['count'], $m($p['mutual'])]; }
        $rows[] = []; $rows[] = ['Mois', 'Nouveaux adhérents', 'Cotisations encaissées', 'Prestations validées', 'Part mutuelle', 'Entrées de trésorerie', 'Sorties de trésorerie'];
        foreach ($r['monthly'] as $mo) { $rows[] = [$mo['label'], $mo['new'], $m($mo['collected']), $mo['prestations'], $m($mo['mutual']), $m($mo['in']), $m($mo['out'])]; }
        if ($d['health']) {
            $rows[] = []; $rows[] = ['Par acte', 'Prestations', 'Part mutuelle'];
            foreach ($r['by_act'] as $a) { $rows[] = [$a['label'], $a['count'], $m($a['mutual'])]; }
        }
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) { fputcsv($out, $row, ';'); }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport-activite-' . $r['from']->format('Ymd') . '-' . $r['to']->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    private function data(Request $request): array
    {
        $periods = ActivityReportService::periods();
        $key = $request->get('periode', 'mois');
        if ($key === 'libre') {
            $this->validateOr(route('reports.index'), $request, ['du' => ['required', 'date'], 'au' => ['required', 'date', 'after_or_equal:du']],
                ['au.after_or_equal' => 'La fin doit suivre le début.']);
            [$from, $to] = [Carbon::parse($request->get('du'))->startOfDay(), Carbon::parse($request->get('au'))->endOfDay()];
        } else {
            $key = array_key_exists($key, $periods) ? $key : 'mois';
            [, $from, $to] = $periods[$key];
        }

        return [
            'report'   => $this->reports->build($from, $to),
            'periods'  => $periods,
            'periode'  => $key,
            'currency' => $this->currency(),
            'health'   => TenantAccess::can('capacite.donnees-sante'),
        ];
    }
}
