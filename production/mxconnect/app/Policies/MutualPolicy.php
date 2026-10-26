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
        return $user->hasRole('super_admin') && in_array($mutual->status, ['pending', 'approved'], true);
    }

    public function reactivate(NetworkUser $user, Mutual $mutual): bool
    {
        return $user->hasRole('super_admin') && $mutual->status === 'suspended';
    }

    /** Fiche d'une mutuelle : comme la liste. */
    public function view(NetworkUser $user, Mutual $mutual): bool
    {
        return $this->viewAny($user);
    }

    /** Créer ou rétablir l'accès d'un administrateur de la mutuelle. */
    public function manageAccess(NetworkUser $user, Mutual $mutual): bool
    {
        return $user->hasRole('super_admin');
    }
}
