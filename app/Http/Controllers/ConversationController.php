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

    private const PER_PAGE = 40;

    public function index(): Response
    {
        $conversations = $this->listConversations();

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => null,
            'messages'           => [],
            'hasMoreMessages'    => false,
            'friends'            => count($conversations) === 0 ? $this->listFriends() : [],
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
        $query = $conversation->messages()->with('user')->orderByDesc('id');

        $beforeId = $request->query('before');
        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }

        $paginated    = $query->limit(self::PER_PAGE + 1)->get();
        $hasMore      = $paginated->count() > self::PER_PAGE;
        $messages     = $paginated->take(self::PER_PAGE)->sortBy('id')->values();

        $mapped = $messages->map(fn ($m) => $this->formatMessage($m));

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
            'messages'        => $mapped->values(),
            'hasMoreMessages' => $hasMore,
            'friends'         => [],
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

        return back();
    }

    /**
     * Lightweight polling: return messages newer than ?after=id as JSON.
     * Frontend polls this every ~12 s when the tab is visible.
     */
    public function poll(Conversation $conversation, Request $request): JsonResponse
    {
        abort_unless(
            $conversation->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        $afterId = (int) $request->query('after', 0);

        $messages = $conversation->messages()
            ->with('user')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        // Mark as read if there are new messages
        if ($messages->isNotEmpty()) {
            $conversation->participants()->updateExistingPivot(auth()->id(), [
                'last_read_at' => now(),
            ]);
        }

        return response()->json(['messages' => $messages]);
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
        return [
            'id'         => $m->id,
            'content'    => $m->content,
            'image_url'  => $m->image_url,
            'created_at' => $m->created_at->toISOString(),
            'is_edited'  => $m->updated_at->gt($m->created_at->addSecond()),
            'is_own'     => $m->user_id === auth()->id(),
            'author'     => [
                'id'           => $m->user->id,
                'name'         => $m->user->name,
                'username'     => $m->user->username,
                'avatar'       => $m->user->avatar,
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
