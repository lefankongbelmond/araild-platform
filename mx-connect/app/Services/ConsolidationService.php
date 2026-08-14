<?php

namespace App\Services;

use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;

/**
 * Cross-mutual consolidation for the network operator (CDC Phase 4, §31).
 *
 * Iterates approved mutuals, gathers each one's membership and financial figures
 * inside its own database, and aggregates them. Money is aggregated PER CURRENCY
 * (mutuals may operate in different currencies, so summing raw minor units across
 * currencies would be meaningless); membership counts are currency-independent and
 * summed globally. The aggregation step is pure and unit-tested.
 */
class ConsolidationService
{
    public function __construct(private ActuarialService $actuarial) {}

    /** Full snapshot: per-mutual rows + aggregates. */
    public function snapshot(int $year): array
    {
        $rows = $this->perMutual($year);

        return ['rows' => $rows, 'totals' => $this->aggregate($rows)];
    }

    /** One row per approved mutual, computed inside its tenant database. */
    public function perMutual(int $year): array
    {
        $currencies = Currency::on('central')->pluck('code', 'id');
        $rows = [];

        Mutual::where('status', 'approved')->orderBy('name')->get()->each(function (Mutual $mutual) use (&$rows, $year, $currencies) {
            $row = [
                'id' => $mutual->id, 'name' => $mutual->name,
                'currency' => $currencies[$mutual->currency_id] ?? '—',
                'reachable' => false,
                'members' => 0, 'active_subscriptions' => 0,
                'premiums' => 0, 'claims' => 0, 'reserves' => 0, 'loss_ratio' => null,
            ];

            try {
                $mutual->run(function () use (&$row, $year) {
                    $row['reachable'] = true;
                    $row['members'] = Member::count();
                    $row['active_subscriptions'] = Subscription::where('status', 'validated')->count();
                    $row['premiums'] = $this->actuarial->collectedContributions($year);
                    $row['claims'] = $this->actuarial->incurredClaims($year);
                    $row['reserves'] = $this->actuarial->reserves();
                    $row['loss_ratio'] = $this->actuarial->lossRatio($year);
                });
            } catch (\Throwable $e) {
                $row['reachable'] = false;
            }

            $rows[] = $row;
        });

        return $rows;
    }

    /**
     * Aggregate rows into network totals. Pure — no DB access.
     * @return array{mutuals:int,reachable:int,members:int,active_subscriptions:int,by_currency:array<string,array{premiums:int,claims:int,reserves:int,loss_ratio:?float}>}
     */
    public function aggregate(array $rows): array
    {
        $totals = [
            'mutuals' => count($rows),
            'reachable' => 0,
            'members' => 0,
            'active_subscriptions' => 0,
            'by_currency' => [],
        ];

        foreach ($rows as $r) {
            if (! empty($r['reachable'])) {
                $totals['reachable']++;
            }
            $totals['members'] += (int) $r['members'];
            $totals['active_subscriptions'] += (int) $r['active_subscriptions'];

            $ccy = $r['currency'] ?? '—';
            $bucket = $totals['by_currency'][$ccy] ?? ['premiums' => 0, 'claims' => 0, 'reserves' => 0, 'loss_ratio' => null];
            $bucket['premiums'] += (int) $r['premiums'];
            $bucket['claims']   += (int) $r['claims'];
            $bucket['reserves'] += (int) $r['reserves'];
            $totals['by_currency'][$ccy] = $bucket;
        }

        // Network loss ratio per currency, computed on the aggregated figures.
        foreach ($totals['by_currency'] as $ccy => $bucket) {
            $totals['by_currency'][$ccy]['loss_ratio'] = $bucket['premiums'] > 0
                ? round($bucket['claims'] / $bucket['premiums'], 4)
                : null;
        }

        return $totals;
    }
}
