<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Friend;
use App\Support\StoresImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    use StoresImages;
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

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeImage($request->file('image'), 'messages');
        }

        $message = $conversation->messages()->create([
            'user_id'   => auth()->id(),
            'content'   => $request->input('content'),
            'image_url' => $imageUrl,
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
        $userId = auth()->id();

        $convList = auth()->user()
            ->conversations()
            ->with(['participants', 'lastMessage'])
            ->orderByDesc('conversations.updated_at')
            ->get();

        if ($convList->isEmpty()) {
            return [];
        }

        // Single query instead of N+1 COUNT per conversation
        $unreadCounts = DB::table('messages')
            ->join('conversation_user', function ($join) use ($userId) {
                $join->on('messages.conversation_id', '=', 'conversation_user.conversation_id')
                     ->where('conversation_user.user_id', '=', $userId);
            })
            ->whereIn('messages.conversation_id', $convList->pluck('id'))
            ->where('messages.user_id', '!=', $userId)
            ->whereRaw('(conversation_user.last_read_at IS NULL OR messages.created_at > conversation_user.last_read_at)')
            ->groupBy('messages.conversation_id')
            ->select('messages.conversation_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'conversation_id');

        return $convList->map(function ($conv) use ($userId, $unreadCounts) {
            $other = $conv->participants->firstWhere('id', '!=', $userId);

            return [
                'id'           => $conv->id,
                'unread_count' => (int) ($unreadCounts->get($conv->id, 0)),
                'last_message' => $conv->lastMessage ? [
                    'content'    => $conv->lastMessage->content,
                    'created_at' => $conv->lastMessage->created_at->toISOString(),
                    'is_own'     => $conv->lastMessage->user_id === $userId,
                ] : null,
                'other_user'   => $other ? [
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'username'     => $other->username,
                    'avatar'       => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ];
        })->values()->toArray();
    }
}
