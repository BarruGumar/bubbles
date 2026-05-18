<?php

namespace App\Policies;

use App\Models\Bubble;
use App\Models\User;

class BubblePolicy
{
    /** Owner or community admin or global admin can manage settings */
    public function manage(User $user, Bubble $bubble): bool
    {
        return $user->isAdmin() || $user->canManageCommunity($bubble);
    }

    /** Same as manage */
    public function update(User $user, Bubble $bubble): bool
    {
        return $this->manage($user, $bubble);
    }

    /** Only owner or global admin can delete the community */
    public function delete(User $user, Bubble $bubble): bool
    {
        return $user->isAdmin() || $user->id === $bubble->user_id;
    }

    /** Owner, community admin/moderator, global moderator/admin */
    public function moderate(User $user, Bubble $bubble): bool
    {
        return $user->isModerator() || $user->canModerateCommunity($bubble);
    }

    /** Only owner, community admin, or global admin can promote/demote */
    public function promote(User $user, Bubble $bubble): bool
    {
        return $user->isAdmin() || $user->canManageCommunity($bubble);
    }
}
