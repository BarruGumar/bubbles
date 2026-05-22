<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPunishment;

class UserPunishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasAdminAccess();
    }

    public function revoke(User $user, UserPunishment $punishment): bool
    {
        // Cannot revoke own punishment; must have admin access; cannot revoke punishment of a site_owner
        if ($punishment->user_id === $user->id) {
            return false;
        }

        if (! $user->hasAdminAccess()) {
            return false;
        }

        // Admin cannot revoke punishment belonging to a site_owner (site_owner protects themselves)
        $target = $punishment->user;
        if ($target && $target->isSiteOwner() && ! $user->isSiteOwner()) {
            return false;
        }

        return true;
    }
}
