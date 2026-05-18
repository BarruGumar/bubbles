<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPunishment;

class UserPunishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function revoke(User $user, UserPunishment $punishment): bool
    {
        return $user->isAdmin() && $punishment->user_id !== $user->id;
    }
}
