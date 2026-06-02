<?php

namespace App\Http\Controllers;

use App\Events\BadgeCountUpdated;
use App\Events\ConversationCreated;
use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\TypingUpdated;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Friend;
use App\Models\Message;
use App\Notifications\MessageReceived;
use App\Support\ImageUploadPresets;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'conversations'      => $skipConversations ? [] : $this->listConversations(),
            'activeConversation' => $this->formatConversation($conversation),
            'messages'           => $mapped->values(),
            'hasMoreMessages'    => $hasMore,
            'friends'            => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['recipient_id' => 'required|integer|exists:users,id']);

        $recipientId = (int) $request->input('recipient_id');
        abort_if($recipientId === auth()->id(), 422);

        $recipient = \App\Models\User::findOrFail($recipientId);
        $me = auth()->user();
        abort_if($me->hasBlocked($recipient) || $me->isBlockedBy($recipient), 422);

        $conversation = Conversation::where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipientId))
            ->first();

        $isNew = false;
        if (! $conversation) {
            $isNew = true;
            $conversation = Conversation::create([]);
            $conversation->participants()->attach([
                auth()->id() => ['last_read_at' => null],
                $recipientId => ['last_read_at' => null],
            ]);
        }

        if ($isNew) {
            $me = auth()->user();
            broadcast(new ConversationCreated($recipientId, [
                'id'           => $conversation->id,
                'type'         => 'direct',
                'unread_count' => 0,
                'last_message' => null,
                'other_user'   => [
                    'id'           => $me->id,
                    'name'         => $me->name,
                    'username'     => $me->username,
                    'avatar'       => $me->avatar,
                    'avatar_color' => $me->avatar_color ?? '#009ac7',
                ],
            ]));
        }

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $imageUrl = null;
        if ($request->hasFile('image')) {
            ['url' => $imageUrl] = $this->storeImageWithMeta(
                $request->file('image'),
                'messages',
                ImageUploadPresets::message()
            );
        }

        $replyToId = $request->input('reply_to_id');
        if ($replyToId && ! $conversation->messages()->where('id', $replyToId)->exists()) {
            abort(422);
        }

        $message = DB::transaction(function () use ($conversation, $request, $imageUrl, $replyToId) {
            $msg = $conversation->messages()->create([
                'user_id' => auth()->id(),
                'content' => $request->input('content'),
                'image_url' => $imageUrl,
                'reply_to_id' => $replyToId,
            ]);

            $conversation->update(['last_message_id' => $msg->id]);

            $conversation->participants()->updateExistingPivot(auth()->id(), [
                'last_read_at' => now(),
            ]);

            return $msg;
        });

        // Notify all other participants (supports both direct and group)
        $conversation->participants()
            ->where('users.id', '!=', auth()->id())
            ->get()
            ->each(fn ($p) => $p->notify(new MessageReceived(
                auth()->user(),
                $conversation,
                $message->content ?? '[imagem]',
            )));

        $message->load(['user', 'replyTo.user']);

        $formatted = $this->formatMessage($message);

        broadcast(new MessageSent($conversation->id, $formatted))->toOthers();

        $conversation->participants()
            ->where('users.id', '!=', auth()->id())
            ->get()
            ->each(fn ($p) => broadcast(new BadgeCountUpdated($p->id, 'messages', 1, $conversation->id)));

        return response()->json(['message' => $formatted]);
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

        // For direct conversations only: return the other participant's last_read_at
        // For groups this is always null (per-user read state shown differently in UI)
        $otherReadAt = null;
        if ($conversation->isDirect()) {
            $otherReadAt = DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $userId)
                ->value('last_read_at');
        }

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

        broadcast(new TypingUpdated(
            $conversation->id,
            $userId,
            auth()->user()->name,
            $request->boolean('is_typing'),
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $userId = auth()->id();

        abort_unless(
            $conversation->participants()->where('user_id', $userId)->exists(),
            403
        );

        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);

        Cache::forget("user:{$userId}:badge:messages");

        broadcast(new MessageRead(
            $conversation->id,
            $userId,
            now()->toISOString(),
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function updateMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === auth()->id(), 403);
        $request->validate(['content' => 'required|string|max:2000']);
        $message->update(['content' => $request->input('content')]);

        $fresh = $message->fresh()->load('user');
        $formatted = $this->formatMessage($fresh);

        broadcast(new MessageUpdated(
            $message->conversation_id,
            $message->id,
            $fresh->content,
            $formatted['is_edited'],
        ))->toOthers();

        return response()->json($formatted);
    }

    public function destroyMessage(Message $message): JsonResponse
    {
        abort_unless($message->user_id === auth()->id(), 403);
        $conv = $message->conversation;
        $conversationId = $conv->id;
        $messageId = $message->id;
        $isLast = $conv->last_message_id === $message->id;
        DB::transaction(function () use ($message, $conv, $isLast) {
            $message->delete();
            if ($isLast) {
                $conv->update(['last_message_id' => $conv->messages()->latest()->value('id')]);
            }
        });

        broadcast(new MessageDeleted($conversationId, $messageId))->toOthers();

        return response()->json(['ok' => true]);
    }

    private function formatConversation(Conversation $conversation): array
    {
        $userId = auth()->id();

        if ($conversation->isDirect()) {
            $other = $conversation->participants()
                ->where('users.id', '!=', $userId)
                ->select('users.id', 'users.name', 'users.username', 'users.avatar', 'users.avatar_color')
                ->first();

            return [
                'id'                => $conversation->id,
                'type'              => 'direct',
                'other_last_read_at' => $other?->pivot?->last_read_at
                    ? \Carbon\Carbon::parse($other->pivot->last_read_at)->toISOString()
                    : null,
                'other_user'        => $other ? [
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'username'     => $other->username,
                    'avatar'       => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ];
        }

        // Group conversation
        $participants = $conversation->participants()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar', 'users.avatar_color')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'username'     => $p->username,
                'avatar'       => $p->avatar,
                'avatar_color' => $p->avatar_color ?? '#009ac7',
                'role'         => $p->pivot->role,
                'last_read_at' => $p->pivot->last_read_at
                    ? \Carbon\Carbon::parse($p->pivot->last_read_at)->toISOString()
                    : null,
            ])
            ->values();

        $userRole = $participants->firstWhere('id', $userId)['role'] ?? 'member';

        return [
            'id'                 => $conversation->id,
            'type'               => 'group',
            'group_name'         => $conversation->name,
            'group_avatar'       => $conversation->avatar,
            'group_description'  => $conversation->description,
            'owner_id'           => $conversation->owner_id,
            'user_role'          => $userRole,
            'participants_count' => $participants->count(),
            'participants'       => $participants,
        ];
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
        return Friend::friendsOf(auth()->id())->toArray();
    }

    private function listConversations(): array
    {
        $userId = auth()->id();

        $convList = auth()->user()
            ->conversations()
            ->with([
                'participants' => fn ($q) => $q->select('users.id', 'users.name', 'users.username', 'users.avatar', 'users.avatar_color'),
                'lastMessage',
            ])
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
            $base = [
                'id'           => $conv->id,
                'type'         => $conv->type ?? 'direct',
                'unread_count' => (int) ($unreadCounts->get($conv->id, 0)),
                'last_message' => $conv->lastMessage ? [
                    'content'    => $conv->lastMessage->content,
                    'created_at' => $conv->lastMessage->created_at->toISOString(),
                    'is_own'     => $conv->lastMessage->user_id === $userId,
                ] : null,
            ];

            if (($conv->type ?? 'direct') === 'group') {
                return array_merge($base, [
                    'group_name'         => $conv->name,
                    'group_avatar'       => $conv->avatar,
                    'participants_count' => $conv->participants->count(),
                    'other_user'         => null,
                ]);
            }

            $other = $conv->participants->firstWhere('id', '!=', $userId);

            return array_merge($base, [
                'other_user' => $other ? [
                    'id'           => $other->id,
                    'name'         => $other->name,
                    'username'     => $other->username,
                    'avatar'       => $other->avatar,
                    'avatar_color' => $other->avatar_color ?? '#009ac7',
                ] : null,
            ]);
        })->values()->toArray();
    }
}
