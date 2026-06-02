<?php

namespace App\Http\Controllers;

use App\Events\BadgeCountUpdated;
use App\Events\FriendshipUpdated;
use App\Models\Friend;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();
        $search = trim(request('search', ''));

        $friends = Friend::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })->where('status', 'accepted')
            ->when($search, function ($q) use ($search, $userId) {
                $q->where(function ($q2) use ($search, $userId) {
                    $q2->where('user_id', $userId)
                        ->whereHas('friend', fn ($u) => $u->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%"));
                })->orWhere(function ($q2) use ($search, $userId) {
                    $q2->where('friend_id', $userId)
                        ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%"));
                });
            })
            ->with(['user', 'friend'])
            ->latest()
            ->paginate(30)
            ->through(function ($f) use ($userId) {
                $other = $f->user_id === $userId ? $f->friend : $f->user;

                return [
                    'friendId'    => $f->id,
                    'id'          => $other->id,
                    'name'        => $other->name,
                    'username'    => $other->username,
                    'avatar'      => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ];
            });

        $received = Friend::where('friend_id', $userId)
            ->where('status', 'pending')
            ->with('user')
            ->limit(50)
            ->get()
            ->map(fn ($f) => [
                'friendId' => $f->id,
                'id' => $f->user->id,
                'name' => $f->user->name,
                'username' => $f->user->username,
                'avatar' => $f->user->avatar,
                'avatar_color' => $f->user->avatar_color ?? '#009ac7',
            ]);

        $sent = Friend::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('friend')
            ->limit(50)
            ->get()
            ->map(fn ($f) => [
                'friendId' => $f->id,
                'id' => $f->friend->id,
                'name' => $f->friend->name,
                'username' => $f->friend->username,
                'avatar' => $f->friend->avatar,
                'avatar_color' => $f->friend->avatar_color ?? '#009ac7',
            ]);

        return Inertia::render('Friends/Index', [
            'friends'  => $friends,
            'received' => $received->values(),
            'sent'     => $sent->values(),
            'search'   => $search,
        ]);
    }

    public function send(string $username): RedirectResponse
    {
        $target = User::where('username', $username)->firstOrFail();
        $user = auth()->user();
        abort_if($user->id === $target->id, 422);
        abort_if($user->hasBlocked($target) || $user->isBlockedBy($target), 422);

        $exists = Friend::where(function ($q) use ($user, $target) {
            $q->where('user_id', $user->id)->where('friend_id', $target->id);
        })->orWhere(function ($q) use ($user, $target) {
            $q->where('user_id', $target->id)->where('friend_id', $user->id);
        })->exists();

        if (! $exists) {
            $friendRecord = Friend::create([
                'user_id'   => $user->id,
                'friend_id' => $target->id,
                'status'    => 'pending',
            ]);

            broadcast(new BadgeCountUpdated($target->id, 'friends', 1));
            NotificationService::send($target, new FriendRequestReceived(auth()->user()));
            broadcast(new FriendshipUpdated($target->id, 'request_received', [
                'friendId'    => $friendRecord->id,
                'id'          => auth()->id(),
                'name'        => auth()->user()->name,
                'username'    => auth()->user()->username,
                'avatar'      => auth()->user()->avatar,
                'avatar_color' => auth()->user()->avatar_color ?? '#009ac7',
            ]));
        }

        return back();
    }

    public function accept(Friend $friend): RedirectResponse
    {
        abort_if($friend->friend_id !== auth()->id(), 403);
        abort_if($friend->status !== 'pending', 422);

        $friend->update(['status' => 'accepted']);

        Friend::clearFriendCaches($friend->user_id, $friend->friend_id);
        Cache::forget("user:{$friend->friend_id}:badge:friends");

        NotificationService::send($friend->user, new FriendRequestAccepted(auth()->user()));
        broadcast(new FriendshipUpdated($friend->user->id, 'request_accepted', [
            'friendId'    => $friend->id,
            'id'          => auth()->id(),
            'name'        => auth()->user()->name,
            'username'    => auth()->user()->username,
            'avatar'      => auth()->user()->avatar,
            'avatar_color' => auth()->user()->avatar_color ?? '#009ac7',
        ]));

        return back();
    }

    public function reject(Friend $friend): RedirectResponse
    {
        abort_if(
            $friend->user_id !== auth()->id() && $friend->friend_id !== auth()->id(),
            403
        );

        Friend::clearFriendCaches($friend->user_id, $friend->friend_id);
        Cache::forget("user:{$friend->friend_id}:badge:friends");

        $friend->delete();

        return back();
    }
}
