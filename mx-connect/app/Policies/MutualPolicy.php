<?php

namespace App\Policies;

use App\Models\Central\Mutual;
use App\Models\Central\NetworkUser;

/**
 * Only super_admin manages mutuals. security_admin and moderator cannot create,
 * approve or suspend — separation of duties at the network level.
 */
class MutualPolicy
{
    public function viewAny(NetworkUser $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'security_admin']);
    }

    public function create(NetworkUser $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function approve(NetworkUser $user, Mutual $mutual): bool
    {
        return $user->hasRole('super_admin') && $mutual->status === 'pending';
    }

    public function suspend(NetworkUser $user, Mutual $mutual): bool
    {
        return $user->hasRole('super_admin') && $mutual->status === 'approved';
    }
}
