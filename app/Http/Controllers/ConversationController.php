<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Friend;
use App\Models\Message;
use App\Notifications\MessageReceived;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    use StoresImages;

    private const PER_PAGE = 20;

    public function index(): Response
    {
        $conversations = $this->listConversations();

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => null,
            'messages' => [],
            'hasMoreMessages' => false,
            'friends' => count($conversations) === 0 ? $this->listFriends() : [],
        ]);
    }

    public function show(Conversation $conversation, Request $request): Response
    {
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

        // Load last PER_PAGE messages; support paging older ones via ?before=id
        $query = $conversation->messages()->with(['user', 'replyTo.user'])->orderByDesc('id');

        $beforeId = $request->query('before');
        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }

        $paginated = $query->limit(self::PER_PAGE + 1)->get();
        $hasMore = $paginated->count() > self::PER_PAGE;
        $messages = $paginated->take(self::PER_PAGE)->sortBy('id')->values();

        $mapped = $messages->map(fn ($m) => $this->formatMessage($m));

        // Skip the expensive conversation list query when Inertia only needs messages/hasMoreMessages
        $partialData = $request->header('X-Inertia-Partial-Data', '');
        $requestedProps = array_filter(array_map('trim', explode(',', $partialData)));
        $skipConversations = !empty($requestedProps) && !in_array('conversations', $requestedProps);

        return Inertia::render('Chat/Index', [
            'conversations' => $skipConversations ? [] : $this->listConversations(),
            'activeConversation' => [
                'id' => $conversation->id,
                'other_last_read_at' => $other?->pivot?->last_read_at
                    ? \Carbon\Carbon::parse($other->pivot->last_read_at)->toISOString()
                    : null,
                'other_user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'username' => $other->username,
                    'avatar' => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ],
            'messages' => $mapped->values(),
            'hasMoreMessages' => $hasMore,
            'friends' => [],
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

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403, 'A tua conta foi banida.');
        abort_if($user->isSuspended(), 403, 'A tua conta está suspensa.');
        abort_if($user->isGloballyMuted(), 403, 'Estás em silêncio global.');

        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeImage($request->file('image'), 'messages');
        }

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
            'image_url' => $imageUrl,
            'reply_to_id' => $request->input('reply_to_id'),
        ]);

        $conversation->update(['last_message_id' => $message->id]);

        $conversation->participants()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        // Notify the other participant (not the sender)
        $recipient = $conversation->participants()
            ->where('users.id', '!=', auth()->id())
            ->first();

        if ($recipient) {
            $recipient->notify(new MessageReceived(
                auth()->user(),
                $conversation,
                $message->content ?? '[imagem]',
            ));
        }

        $message->load(['user', 'replyTo.user']);

        return response()->json(['message' => $this->formatMessage($message)]);
    }

    /**
     * Lightweight polling: return messages newer than ?after=id as JSON.
     * Frontend polls this every ~3 s when the tab is visible.
     */
    public function poll(Conversation $conversation, Request $request): JsonResponse
    {
        $userId = auth()->id();

        abort_unless(
            DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->exists(),
            403
        );

        $afterId = (int) $request->query('after', 0);

        $messages = $conversation->messages()
            ->with(['user', 'replyTo.user'])
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        if ($messages->isNotEmpty()) {
            DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->update(['last_read_at' => now()]);
        }

        // Direct pivot query — avoids JOIN with users table
        $otherReadAt = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->value('last_read_at');

        // Read typing state for other participants from cache (no DB query)
        $otherUserIds = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id');

        $typingUsers = $otherUserIds->map(function ($uid) use ($conversation) {
            return cache()->get("typing:{$conversation->id}:{$uid}");
        })->filter()->values();

        return response()->json([
            'messages' => $messages,
            'other_last_read_at' => $otherReadAt
                ? \Carbon\Carbon::parse($otherReadAt)->toISOString()
                : null,
            'typing_users' => $typingUsers,
        ]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = auth()->id();

        abort_unless(
            DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->exists(),
            403
        );

        $request->validate(['is_typing' => 'required|boolean']);

        $key = "typing:{$conversation->id}:{$userId}";

        if ($request->boolean('is_typing')) {
            cache()->put($key, ['id' => $userId, 'name' => auth()->user()->name], 8);
        } else {
            cache()->forget($key);
        }

        return response()->json(['ok' => true]);
    }

    public function updateMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === auth()->id(), 403);
        $request->validate(['content' => 'required|string|max:2000']);
        $message->update(['content' => $request->input('content')]);

        return response()->json($this->formatMessage($message->fresh()->load('user')));
    }

    public function destroyMessage(Message $message): JsonResponse
    {
        abort_unless($message->user_id === auth()->id(), 403);
        $conv = $message->conversation;
        $isLast = $conv->last_message_id === $message->id;
        $message->delete();
        if ($isLast) {
            $conv->update(['last_message_id' => $conv->messages()->latest()->value('id')]);
        }

        return response()->json(['ok' => true]);
    }

    private function formatMessage($m): array
    {
        $replyTo = null;
        if ($m->reply_to_id && $m->replyTo) {
            $rt = $m->replyTo;
            $replyTo = [
                'id' => $rt->id,
                'content' => $rt->content,
                'image_url' => $rt->image_url,
                'author_name' => $rt->user->name ?? '?',
            ];
        }

        return [
            'id' => $m->id,
            'content' => $m->content,
            'image_url' => $m->image_url,
            'reply_to' => $replyTo,
            'created_at' => $m->created_at->toISOString(),
            'is_edited' => $m->updated_at->gt($m->created_at->addSecond()),
            'is_own' => $m->user_id === auth()->id(),
            'author' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'username' => $m->user->username,
                'avatar' => $m->user->avatar,
                'avatar_color' => $m->user->avatar_color ?? '#009ac7',
            ],
        ];
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
                    'id' => $other->id,
                    'name' => $other->name,
                    'username' => $other->username,
                    'avatar' => $other->avatar,
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
                'id' => $conv->id,
                'unread_count' => (int) ($unreadCounts->get($conv->id, 0)),
                'last_message' => $conv->lastMessage ? [
                    'content' => $conv->lastMessage->content,
                    'created_at' => $conv->lastMessage->created_at->toISOString(),
                    'is_own' => $conv->lastMessage->user_id === $userId,
                ] : null,
                'other_user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'username' => $other->username,
                    'avatar' => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ];
        })->values()->toArray();
    }
}
