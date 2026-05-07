<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user'                  => $request->user(),
                'pending_friends_count'  => $request->user()
                    ? \App\Models\Friend::where('friend_id', $request->user()->id)
                        ->where('status', 'pending')
                        ->count()
                    : 0,
                'unread_messages_count' => $request->user()
                    ? \App\Models\Message::join('conversation_user as cu', function ($join) use ($request) {
                        $join->on('cu.conversation_id', '=', 'messages.conversation_id')
                             ->where('cu.user_id', $request->user()->id);
                    })
                    ->where('messages.user_id', '!=', $request->user()->id)
                    ->where(function ($q) {
                        $q->whereNull('cu.last_read_at')
                          ->orWhereRaw('messages.created_at > cu.last_read_at');
                    })
                    ->count()
                    : 0,
            ],
        ];
    }
}
