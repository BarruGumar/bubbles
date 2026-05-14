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
        if ($user->id === $post->user_id || $user->isModerator()) {
            return true;
        }

        $bubble = $post->bubble;

        return $bubble && $user->id === $bubble->user_id;
    }
}
