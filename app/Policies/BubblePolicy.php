<?php

namespace App\Policies;

use App\Models\Bubble;
use App\Models\User;

class BubblePolicy
{
    public function manage(User $user, Bubble $bubble): bool
    {
        return $user->id === $bubble->user_id;
    }
}
