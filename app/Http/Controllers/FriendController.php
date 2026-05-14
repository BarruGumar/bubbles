<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $friends = Friend::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })->where('status', 'accepted')
            ->with(['user', 'friend'])
            ->get()
            ->map(function ($f) use ($userId) {
                $other = $f->user_id === $userId ? $f->friend : $f->user;
                return [
                    'friendId'     => $f->id,
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'username'     => $other->username,
                    'avatar'       => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ];
            });

        $received = Friend::where('friend_id', $userId)
            ->where('status', 'pending')
            ->with('user')
            ->get()
            ->map(fn ($f) => [
                'friendId'     => $f->id,
                'id'           => $f->user->id,
                'name'         => $f->user->name,
                'username'     => $f->user->username,
                'avatar'       => $f->user->avatar,
                'avatar_color' => $f->user->avatar_color ?? '#009ac7',
            ]);

        $sent = Friend::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('friend')
            ->get()
            ->map(fn ($f) => [
                'friendId'     => $f->id,
                'id'           => $f->friend->id,
                'name'         => $f->friend->name,
                'username'     => $f->friend->username,
                'avatar'       => $f->friend->avatar,
                'avatar_color' => $f->friend->avatar_color ?? '#009ac7',
            ]);

        return Inertia::render('Friends/Index', [
            'friends'  => $friends->values(),
            'received' => $received->values(),
            'sent'     => $sent->values(),
        ]);
    }

    public function send(string $username): RedirectResponse
    {
        $target = User::where('username', $username)->firstOrFail();
        abort_if(auth()->id() === $target->id, 422);

        $exists = Friend::where(function ($q) use ($target) {
            $q->where('user_id', auth()->id())->where('friend_id', $target->id);
        })->orWhere(function ($q) use ($target) {
            $q->where('user_id', $target->id)->where('friend_id', auth()->id());
        })->exists();

        if (! $exists) {
            Friend::create([
                'user_id'   => auth()->id(),
                'friend_id' => $target->id,
                'status'    => 'pending',
            ]);
            $target->notify(new FriendRequestReceived(auth()->user()));
        }

        return back();
    }

    public function accept(Friend $friend): RedirectResponse
    {
        abort_if($friend->friend_id !== auth()->id(), 403);
        abort_if($friend->status !== 'pending', 422);

        $friend->update(['status' => 'accepted']);

        // Notify the original sender that their request was accepted
        $friend->user->notify(new FriendRequestAccepted(auth()->user()));

        return back();
    }

    public function reject(Friend $friend): RedirectResponse
    {
        abort_if(
            $friend->user_id !== auth()->id() && $friend->friend_id !== auth()->id(),
            403
        );

        $friend->delete();

        return back();
    }
}
