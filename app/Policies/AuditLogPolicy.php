<?php

namespace App\Policies;

use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminAccess();
    }

    public function delete(User $user): bool
    {
        return $user->isSiteOwner();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSiteOwner();
    }
}
