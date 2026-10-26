<?php

namespace App\Http\Controllers\MemberApi;

use App\Models\Central\MemberLink;
use App\Models\Central\PublicAccount;
use App\Models\Tenant\Member;

/**
 * Resolves the tenant Member for the authenticated PublicAccount within the
 * current mutual (subdomain). The link lives centrally; the member lives in the
 * tenant DB. Returns null if this account isn't linked to a member here.
 */
trait ResolvesMember
{
    protected function currentMember(?PublicAccount $account): ?Member
    {
        if (! $account) {
            return null;
        }

        $link = MemberLink::where('public_account_id', $account->id)
            ->where('mutual_id', tenant('id'))->first();

        return $link ? Member::find($link->member_id) : null;
    }

    /** Resolve the member or abort 403 if this account isn't a member here. */
    protected function requireMember(\Illuminate\Http\Request $request): Member
    {
        $member = $this->currentMember($request->user());
        abort_unless($member, 403, __('mxconnect.pwa.not_a_member'));

        return $member;
    }
}
