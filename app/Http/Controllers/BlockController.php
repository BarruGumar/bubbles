<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\RedirectResponse;

class BlockController extends Controller
{
    public function store(string $username): RedirectResponse
    {
        $target = User::where('username', $username)->firstOrFail();
        $user = auth()->user();

        abort_if($target->id === $user->id, 422, 'Não podes bloquear-te a ti mesmo.');

        if (! $user->hasBlocked($target)) {
            UserBlock::create(['blocker_id' => $user->id, 'blocked_id' => $target->id]);

            // Remove any existing friendship between the two users
            Friend::where(function ($q) use ($user, $target) {
                $q->where('user_id', $user->id)->where('friend_id', $target->id);
            })->orWhere(function ($q) use ($user, $target) {
                $q->where('user_id', $target->id)->where('friend_id', $user->id);
            })->delete();

            Friend::clearFriendCaches($user->id, $target->id);
        }

        return back();
    }

    public function destroy(string $username): RedirectResponse
    {
        $target = User::where('username', $username)->firstOrFail();

        UserBlock::where('blocker_id', auth()->id())
            ->where('blocked_id', $target->id)
            ->delete();

        return back();
    }
}
