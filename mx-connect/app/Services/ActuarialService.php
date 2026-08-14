<?php

namespace App\Services;

use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\TreasuryMovement;
use Illuminate\Support\Carbon;

/**
 * Actuarial & solvency indicators for a mutual (CDC Phase 2, §21).
 *
 * Definitions used:
 *   - collected contributions (premiums) = sum of contribution_payments in the period;
 *   - incurred claims = sum of VALIDATED prestations' mutual part, by care date;
 *   - loss ratio (S/P) = incurred claims / collected contributions;
 *   - technical result = collected contributions − incurred claims;
 *   - reserves = net treasury balance (all inflows − all outflows);
 *   - required reserve = average monthly incurred claims × reserve_months (config);
 *   - solvency ratio = reserves / required reserve.
 * All amounts are integer minor units.
 */
class ActuarialService
{
    public function collectedContributions(int $year): int
    {
        return (int) ContributionPayment::whereYear('paid_on', $year)->sum('amount_minor');
    }

    public function incurredClaims(int $year): int
    {
        return (int) Prestation::where('status', 'validated')->whereYear('care_date', $year)->sum('mutual_part_minor');
    }

    /** Loss ratio (claims / premiums). Null when no premiums were collected. */
    public function lossRatio(int $year): ?float
    {
        $premiums = $this->collectedContributions($year);
        if ($premiums === 0) {
            return null;
        }
        return round($this->incurredClaims($year) / $premiums, 4);
    }

    public function technicalResult(int $year): int
    {
        return $this->collectedContributions($year) - $this->incurredClaims($year);
    }

    /** Net accumulated funds available (all treasury inflows − outflows). */
    public function reserves(): int
    {
        $in  = (int) TreasuryMovement::where('direction', 'inflow')->sum('amount_minor');
        $out = (int) TreasuryMovement::where('direction', 'outflow')->sum('amount_minor');
        return $in - $out;
    }

    /** Prudential reserve = average monthly claims (trailing 12 months) × reserve_months. */
    public function requiredReserve(): int
    {
        $months = (int) config('mxconnect.actuarial.reserve_months', 3);
        $since = Carbon::now()->subMonths(12)->startOfMonth();

        $trailingClaims = (int) Prestation::where('status', 'validated')
            ->where('care_date', '>=', $since->toDateString())->sum('mutual_part_minor');

        $avgMonthly = intdiv($trailingClaims, 12);
        return $avgMonthly * $months;
    }

    /** Solvency ratio = reserves / required reserve. Null when no reserve is required. */
    public function solvencyRatio(): ?float
    {
        $required = $this->requiredReserve();
        if ($required <= 0) {
            return null;
        }
        return round($this->reserves() / $required, 4);
    }

    /** Loss-ratio traffic light: healthy | watch | critical. */
    public function lossRatioBand(?float $ratio): string
    {
        if ($ratio === null) {
            return 'unknown';
        }
        $t = config('mxconnect.actuarial.loss_ratio');
        if ($ratio < $t['healthy']) {
            return 'healthy';
        }
        return $ratio <= $t['watch'] ? 'watch' : 'critical';
    }

    /** Monthly premiums vs claims for a year (for the chart). */
    public function monthlySeries(int $year): array
    {
        $series = [];
        for ($m = 1; $m <= 12; $m++) {
            $premiums = (int) ContributionPayment::whereYear('paid_on', $year)->whereMonth('paid_on', $m)->sum('amount_minor');
            $claims = (int) Prestation::where('status', 'validated')
                ->whereYear('care_date', $year)->whereMonth('care_date', $m)->sum('mutual_part_minor');
            $series[] = ['month' => $m, 'premiums' => $premiums, 'claims' => $claims];
        }
        return $series;
    }
}
