<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\Friend;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'new_punishment' => function () use ($user) {
                if (! $user) {
                    return null;
                }
                // Show unnotified punishments even if expired — user deserves to know it happened.
                // Exclude only revoked ones (admin undid it, no need to alarm the user).
                $p = $user->punishments()
                    ->whereNull('notified_at')
                    ->whereNull('revoked_at')
                    ->latest()
                    ->first();
                if (! $p) {
                    return null;
                }
                return [
                    'id'      => $p->id,
                    'type'    => $p->type,
                    'reason'  => $p->reason,
                    'ends_at' => $p->ends_at?->toIso8601String(),
                ];
            },
            'active_announcements' => function () use ($user) {
                if (! $user) {
                    return [];
                }

                return Announcement::active()
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn ($a) => [
                        'id'         => $a->id,
                        'title'      => $a->title,
                        'body'       => $a->body,
                        'type'       => $a->type,
                        'created_at' => $a->created_at->toIso8601String(),
                    ])
                    ->all();
            },
            'auth' => [
                'user' => $user ? [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'username'          => $user->username,
                    'bio'               => $user->bio,
                    'avatar'            => $user->avatar,
                    'avatar_color'      => $user->avatar_color,
                    'banner'            => $user->banner,
                    'role'              => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
                'pending_friends_count' => $user
                    ? Cache::remember("user:{$user->id}:badge:friends", 60, fn () =>
                        Friend::where('friend_id', $user->id)
                            ->where('status', 'pending')
                            ->count()
                    )
                    : 0,
                'unread_messages_count' => $user
                    ? Cache::remember("user:{$user->id}:badge:messages", 60, fn () =>
                        Message::join('conversation_user as cu', function ($join) use ($user) {
                            $join->on('cu.conversation_id', '=', 'messages.conversation_id')
                                ->where('cu.user_id', $user->id);
                        })
                            ->where('messages.user_id', '!=', $user->id)
                            ->where(function ($q) {
                                $q->whereNull('cu.last_read_at')
                                    ->orWhereRaw('messages.created_at > cu.last_read_at');
                            })
                            ->count()
                    )
                    : 0,
                'unread_notifications_count' => $user
                    ? Cache::remember("user:{$user->id}:badge:notifications", 60, fn () =>
                        $user->unreadNotifications()
                            ->where('type', '!=', \App\Notifications\MessageReceived::class)
                            ->count()
                    )
                    : 0,
            ],
        ];
    }
}
