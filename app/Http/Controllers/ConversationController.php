<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Friend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(): Response
    {
        $conversations = $this->listConversations();

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => null,
            'messages'           => [],
            'friends'            => count($conversations) === 0 ? $this->listFriends() : [],
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        // show() never needs the friends list — conversations exist by definition
        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $conversation->participants()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        $other = $conversation->participants()
            ->where('users.id', '!=', auth()->id())
            ->first();

        $messages = $conversation->messages()
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'image_url'  => $m->image_url,
                'created_at' => $m->created_at->toISOString(),
                'is_own'     => $m->user_id === auth()->id(),
                'author'     => [
                    'id'           => $m->user->id,
                    'name'         => $m->user->name,
                    'username'     => $m->user->username,
                    'avatar'       => $m->user->avatar,
                    'avatar_color' => $m->user->avatar_color ?? '#009ac7',
                ],
            ]);

        return Inertia::render('Chat/Index', [
            'conversations'      => $this->listConversations(),
            'activeConversation' => [
                'id'         => $conversation->id,
                'other_user' => $other ? [
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'username'     => $other->username,
                    'avatar'       => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ],
            'messages' => $messages,
            'friends'  => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['recipient_id' => 'required|integer|exists:users,id']);

        $recipientId = (int) $request->input('recipient_id');
        abort_if($recipientId === auth()->id(), 422);

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipientId))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([]);
            $conversation->participants()->attach([
                auth()->id() => ['last_read_at' => null],
                $recipientId => ['last_read_at' => null],
            ]);
        }

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function storeMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $request->validate(['content' => 'required|string|max:2000']);

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        $conversation->update(['last_message_id' => $message->id]);

        $conversation->participants()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        return back();
    }

    private function listFriends(): array
    {
        $userId = auth()->id();

        return Friend::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })
        ->where('status', 'accepted')
        ->with(['user', 'friend'])
        ->get()
        ->map(function ($f) use ($userId) {
            $other = $f->user_id === $userId ? $f->friend : $f->user;
            return [
                'id'           => $other->id,
                'name'         => $other->name,
                'username'     => $other->username,
                'avatar'       => $other->avatar,
                'avatar_color' => $other->avatar_color ?? '#009ac7',
            ];
        })
        ->values()
        ->toArray();
    }

    private function listConversations(): array
    {
        return auth()->user()
            ->conversations()
            ->with(['participants', 'lastMessage'])
            ->orderByDesc('conversations.updated_at')
            ->get()
            ->map(function ($conv) {
                $pivot       = $conv->participants->firstWhere('id', auth()->id())?->pivot;
                $other       = $conv->participants->firstWhere('id', '!=', auth()->id());
                $unreadCount = $conv->messages()
                    ->where('user_id', '!=', auth()->id())
                    ->when(
                        $pivot?->last_read_at,
                        fn ($q, $dt) => $q->where('created_at', '>', $dt)
                    )
                    ->count();

                return [
                    'id'           => $conv->id,
                    'unread_count' => $unreadCount,
                    'last_message' => $conv->lastMessage ? [
                        'content'    => $conv->lastMessage->content,
                        'created_at' => $conv->lastMessage->created_at->toISOString(),
                        'is_own'     => $conv->lastMessage->user_id === auth()->id(),
                    ] : null,
                    'other_user'   => $other ? [
                        'id'           => $other->id,
                        'name'         => $other->name,
                        'username'     => $other->username,
                        'avatar'       => $other->avatar,
                        'avatar_color' => $other->avatar_color ?? '#009ac7',
                    ] : null,
                ];
            })
            ->values()
            ->toArray();
    }
}
