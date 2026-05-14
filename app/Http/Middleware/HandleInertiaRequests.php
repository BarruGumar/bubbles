<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
                'error'  => $request->session()->get('error'),
            ],
            'auth' => [
                'user'                      => $user,
                'pending_friends_count'      => $user
                    ? \App\Models\Friend::where('friend_id', $user->id)
                        ->where('status', 'pending')
                        ->count()
                    : 0,
                'unread_messages_count'     => $user
                    ? \App\Models\Message::join('conversation_user as cu', function ($join) use ($user) {
                        $join->on('cu.conversation_id', '=', 'messages.conversation_id')
                             ->where('cu.user_id', $user->id);
                    })
                    ->where('messages.user_id', '!=', $user->id)
                    ->where(function ($q) {
                        $q->whereNull('cu.last_read_at')
                          ->orWhereRaw('messages.created_at > cu.last_read_at');
                    })
                    ->count()
                    : 0,
                'unread_notifications_count' => $user
                    ? $user->unreadNotifications()->count()
                    : 0,
            ],
        ];
    }
}
