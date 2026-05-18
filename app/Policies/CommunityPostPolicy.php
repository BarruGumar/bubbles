<?php

namespace App\Policies;

use App\Models\CommunityPost;
use App\Models\User;

class CommunityPostPolicy
{
    public function update(User $user, CommunityPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, CommunityPost $post): bool
    {
        // Always: author can delete own post
        if ($user->id === $post->user_id) {
            return true;
        }

        // Global admin/moderator can delete anything
        if ($user->isModerator()) {
            return true;
        }

        $bubble = $post->bubble;
        if (! $bubble) {
            return false;
        }

        // Community owner can always delete
        if ($user->id === $bubble->user_id) {
            return true;
        }

        // Community admin/moderator can delete member posts
        // but NOT posts by the owner or other admins
        $actorRole  = $user->communityRole($bubble);
        $authorRole = $post->user ? $post->user->communityRole($bubble) : 'member';

        if (! in_array($actorRole, ['admin', 'moderator'])) {
            return false;
        }

        // Moderator cannot delete admin/owner content
        if ($actorRole === 'moderator' && in_array($authorRole, ['owner', 'admin'])) {
            return false;
        }

        // Community admin cannot delete owner content
        if ($actorRole === 'admin' && $authorRole === 'owner') {
            return false;
        }

        return true;
    }
}
