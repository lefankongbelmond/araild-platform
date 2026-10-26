<?php

use App\Models\Central\MemberLink;
use Illuminate\Support\Facades\Broadcast;

/**
 * Private feed channel per mutual: tenant.{tenantId}.feed (CDC Phase 5).
 * Authorised only for an account linked to a member IN THIS tenant, and only when
 * the channel's tenant id matches the tenant the request was resolved into — a
 * member of one mutual can never subscribe to another mutual's feed.
 */
Broadcast::channel('tenant.{tenantId}.feed', function ($account, string $tenantId) {
    if (! function_exists('tenant') || ! tenant() || tenant('id') !== $tenantId) {
        return false;
    }

    return MemberLink::where('public_account_id', $account->id)
        ->where('mutual_id', $tenantId)
        ->exists();
});
