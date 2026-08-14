<?php

namespace App\Services;

use App\Models\Tenant\Member;
use App\Models\Tenant\GuaranteeVersion;

/**
 * Single source of truth for the 8 rights checks (CDC §16).
 * Called both when opening a care claim AND when a provider scans the QR card.
 * The QR path returns only the verdict + minimal info — NEVER medical data.
 */
class RightsVerificationService
{
    /**
     * @return array{ok:bool, reasons:array<string>}
     */
    public function verify(Member $member, int $guaranteeId, string $careDate, ?int $actTypeId = null, ?int $providerId = null): array
    {
        $reasons = [];

        // 1. member active
        if ($member->status->value !== 'active') {
            $reasons[] = 'member_inactive';
        }

        // 2. contribution up to date (no overdue schedule on any validated subscription)
        $hasArrears = $member->subscriptions()
            ->where('status', 'validated')
            ->whereHas('schedules', fn ($q) => $q->where('status', 'overdue'))
            ->exists();
        if ($hasArrears) {
            $reasons[] = 'contribution_arrears';
        }

        // 3. applicable guarantee version on the care date
        $version = GuaranteeVersion::applicableOn($guaranteeId, $careDate);
        if (! $version) {
            $reasons[] = 'no_active_guarantee';
        }

        // 4. observation period elapsed
        $subscription = $member->subscriptions()
            ->where('status', 'validated')
            ->latest('effective_date')->first();
        if ($subscription && $subscription->observation_ends_on
            && $careDate < $subscription->observation_ends_on->toDateString()) {
            $reasons[] = 'observation_period';
        }

        // 5. act covered (if the version lists exclusions / covered acts)
        if ($version && $actTypeId && is_array($version->exclusions)
            && in_array($actTypeId, $version->exclusions, true)) {
            $reasons[] = 'act_excluded';
        }

        // 6. provider authorised for this version's network
        if ($version && $providerId && is_array($version->provider_network)
            && ! in_array($providerId, $version->provider_network, true)) {
            $reasons[] = 'provider_not_authorised';
        }

        // (7 ceiling availability, 8 dependent status) checked where the amount is known.

        return ['ok' => count($reasons) === 0, 'reasons' => $reasons];
    }
}
