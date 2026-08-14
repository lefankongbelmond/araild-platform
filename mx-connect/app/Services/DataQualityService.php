<?php

namespace App\Services;

use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Data-quality score (CDC Phase 2, §26).
 *
 * Runs a set of checks over member and subscription data, each producing a pass
 * rate (passed / total). The overall score is the weighted average of the rates
 * (weights in config), expressed 0–100. Checks with no applicable rows are skipped
 * so an empty mutual isn't unfairly scored.
 */
class DataQualityService
{
    /**
     * @return array{score:int, checks:array<int,array{key:string,passed:int,total:int,rate:float,weight:int}>}
     */
    public function evaluate(): array
    {
        $weights = config('mxconnect.data_quality.weights');
        $checks = [];

        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();

        // Completeness / validity on members.
        $checks[] = $this->check('member_phone', Member::whereNotNull('phone')->where('phone', '!=', '')->count(), $totalMembers, $weights);
        $checks[] = $this->check('member_id_document', Member::whereNotNull('id_document')->count(), $totalMembers, $weights);
        $checks[] = $this->check('member_antenna', Member::whereNotNull('antenna_id')->count(), $totalMembers, $weights);
        $checks[] = $this->check('member_birth_date', Member::whereNotNull('birth_date')->count(), $totalMembers, $weights);

        // Consistency: active members should carry a subscription.
        $activeWithSub = Member::where('status', 'active')
            ->whereIn('id', Subscription::select('member_id'))->count();
        $checks[] = $this->check('active_has_subscription', $activeWithSub, $activeMembers, $weights);

        // Consistency: subscriptions should have generated schedules.
        $totalSubs = Subscription::count();
        $subsWithSchedule = Subscription::whereIn('id', DB::table('contribution_schedules')->select('subscription_id'))->count();
        $checks[] = $this->check('subscription_has_schedule', $subsWithSchedule, $totalSubs, $weights);

        // Weighted score over applicable checks only.
        $applicable = array_filter($checks, fn ($c) => $c['total'] > 0);
        $weightSum = array_sum(array_column($applicable, 'weight'));
        $score = $weightSum > 0
            ? (int) round(array_sum(array_map(fn ($c) => $c['rate'] * $c['weight'], $applicable)) / $weightSum * 100)
            : 0;

        return ['score' => $score, 'checks' => $checks];
    }

    /** @return array{key:string,passed:int,total:int,rate:float,weight:int} */
    private function check(string $key, int $passed, int $total, array $weights): array
    {
        return [
            'key'    => $key,
            'passed' => $passed,
            'total'  => $total,
            'rate'   => $total > 0 ? round($passed / $total, 4) : 0.0,
            'weight' => (int) ($weights[$key] ?? 1),
        ];
    }
}
