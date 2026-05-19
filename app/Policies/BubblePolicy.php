<?php

namespace App\Policies;

use App\Models\Bubble;
use App\Models\User;

class BubblePolicy
{
    /** Owner or community admin or global admin/site_owner can manage settings */
    public function manage(User $user, Bubble $bubble): bool
    {
        return $user->hasAdminAccess() || $user->canManageCommunity($bubble);
    }

    /** Same as manage */
    public function update(User $user, Bubble $bubble): bool
    {
        return $this->manage($user, $bubble);
    }

    /** Only owner, global admin, or site_owner can delete the community */
    public function delete(User $user, Bubble $bubble): bool
    {
        return $user->hasAdminAccess() || $user->id === $bubble->user_id;
    }

    /** Owner, community admin/moderator, global moderator/admin/site_owner */
    public function moderate(User $user, Bubble $bubble): bool
    {
        return $user->hasModerationAccess() || $user->canModerateCommunity($bubble);
    }

    /** Only owner, community admin, or global admin/site_owner can promote/demote */
    public function promote(User $user, Bubble $bubble): bool
    {
        return $user->hasAdminAccess() || $user->canManageCommunity($bubble);
    }
}
