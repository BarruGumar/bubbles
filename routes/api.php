<?php

use App\Http\Controllers\BubbleController;
use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;

// Public read endpoints
Route::get('/bubbles', [BubbleController::class, 'index']);
Route::get('/connections', [ConnectionController::class, 'index']);
Route::get('/friend-connections', [BubbleController::class, 'friendConnections']);

Route::post('/login', function (Illuminate\Http\Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Credenciais incorrectas.'
        ], 401);
    }

    $token = $user->createToken('bubbles-mobile')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'email'    => $user->email,
            'avatar'   => $user->avatar,
            'role'     => $user->role,
        ],
    ]);
});

Route::post('/register', function (Illuminate\Http\Request $request) {
    $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $colors = ['#009ac7','#7c3aed','#059669','#dc2626','#d97706','#db2777'];

    $user = \App\Models\User::create([
        'name'         => $request->name,
        'email'        => $request->email,
        'password'     => $request->password,
        'username'     => \App\Models\User::generateUsername($request->name),
        'avatar_color' => $colors[array_rand($colors)],
    ]);

    $token = $user->createToken('bubbles-mobile')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'email'    => $user->email,
            'avatar'   => $user->avatar,
            'role'     => $user->role,
        ],
    ], 201);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bubbles', [BubbleController::class, 'store'])
        ->middleware('throttle:create-community');
    Route::put('/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::patch('/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::delete('/bubbles/{bubble}', [BubbleController::class, 'destroy']);

    Route::get('/mobile/feed', function () {
        $userId = auth()->id();
        $posts = \App\Models\Post::with(['user'])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'content'        => $p->content,
                'image'          => $p->image,
                'likes'          => $p->likes_count,
                'liked_by_me'    => $p->likes()->where('user_id', $userId)->exists(),
                'comments_count' => $p->comments_count,
                'created_at'     => $p->created_at->diffForHumans(),
                'author'         => [
                    'id'           => $p->user->id,
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar'       => $p->user->avatar,
                    'avatar_color' => $p->user->avatar_color,
                ],
            ]);

        return response()->json($posts);
    });

    Route::post('/logout', function (Illuminate\Http\Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'ok']);
    });

    Route::get('/mobile/conversations/{id}', function ($id) {
        $user = auth()->user();
        $conversation = $user->conversations()->findOrFail($id);

        $messages = $conversation->messages()
            ->with('user')
            ->latest()
            ->limit(60)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => [
                'id'        => $m->id,
                'content'   => $m->content,
                'image_url' => $m->image_url,
                'is_mine'   => $m->user_id === $user->id,
                'created_at'=> $m->created_at->format('H:i'),
                'author'    => [
                    'id'           => $m->user->id,
                    'name'         => $m->user->name,
                    'avatar'       => $m->user->avatar,
                    'avatar_color' => $m->user->avatar_color,
                ],
            ]);

        return response()->json($messages);
    });

    Route::post('/mobile/conversations/{id}/messages', function (Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        $conversation = $user->conversations()->findOrFail($id);

        $request->validate([
            'content'   => 'nullable|string|max:5000',
            'image_url' => 'nullable|url',
        ]);
        if (!$request->content && !$request->image_url) {
            return response()->json(['message' => 'Mensagem vazia.'], 422);
        }

        $message = $conversation->messages()->create([
            'user_id'   => $user->id,
            'content'   => $request->content ?? '',
            'image_url' => $request->image_url,
        ]);

        $conversation->touch();

        $formatted = [
            'id'        => $message->id,
            'content'   => $message->content,
            'image_url' => null,
            'is_mine'   => true,
            'created_at'=> $message->created_at->format('H:i'),
            'author'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'avatar'       => $user->avatar,
                'avatar_color' => $user->avatar_color,
            ],
        ];

        broadcast(new \App\Events\MessageSent($conversation->id, $formatted))->toOthers();

        return response()->json($formatted, 201);
    });

    Route::post('/mobile/conversations/{id}/typing', function (Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        $isTyping = (bool) $request->input('is_typing', false);
        broadcast(new \App\Events\TypingUpdated($id, $user->id, $user->name, $isTyping))->toOthers();
        return response()->json(['ok' => true]);
    });

    Route::post('/mobile/broadcasting/auth', function (Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

    Route::post('/mobile/posts/{id}/like', function ($id) {
        $user = auth()->user();
        $post = \App\Models\Post::findOrFail($id);

        $existing = $post->likes()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $user->id, 'type' => 'like']);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes' => $post->likes()->count(),
        ]);
    });

    Route::get('/mobile/profile', function () {
    $user = auth()->user();

    return response()->json([
        'id'         => $user->id,
        'name'       => $user->name,
        'username'   => $user->username,
        'email'      => $user->email,
        'bio'        => $user->bio,
        'avatar'     => $user->avatar,
        'avatar_color' => $user->avatar_color,
        'banner'     => $user->banner,
        'role'       => $user->role,
        'created_at' => $user->created_at->format('M Y'),
        'posts_count' => $user->posts()->count(),
        'friends_count' => $user->sentFriendRequests()
            ->where('status', 'accepted')->count() +
            $user->receivedFriendRequests()
            ->where('status', 'accepted')->count(),
    ]);
});

    Route::put('/mobile/profile', function (Illuminate\Http\Request $request) {
        $user = auth()->user();
        $request->validate([
            'name'     => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'bio'      => 'nullable|string|max:500',
            'avatar'   => 'nullable|url',
            'banner'   => 'nullable|url',
        ]);

        $data = $request->only('bio', 'avatar', 'banner');
        if ($request->has('name')) $data['name'] = $request->name;
        if ($request->has('username')) $data['username'] = $request->username;

        $user->update($data);

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'username'     => $user->username,
            'email'        => $user->email,
            'bio'          => $user->bio,
            'avatar'       => $user->avatar,
            'avatar_color' => $user->avatar_color,
            'banner'       => $user->banner,
            'role'         => $user->role,
        ]);
    });

    Route::post('/mobile/upload', function (Illuminate\Http\Request $request) {
        $request->validate(['file' => 'required|image|max:5120']);

        $path = $request->file('file')->store('uploads/mobile', 'public');
        $url = \Illuminate\Support\Facades\Storage::url($path);

        return response()->json(['url' => $url], 201);
    });

Route::get('/mobile/conversations', function () {
    $user = auth()->user();

    $conversations = $user->conversations()
        ->with(['lastMessage.user', 'participants'])
        ->latest('updated_at')
        ->get()
        ->map(fn($c) => [
            'id'           => $c->id,
            'type'         => $c->type,
            'name'         => $c->type === 'group' ? $c->name : null,
            'last_message' => $c->lastMessage ? [
                'content'    => $c->lastMessage->content,
                'created_at' => $c->lastMessage->created_at->diffForHumans(),
                'author'     => $c->lastMessage->user->name,
            ] : null,
            'participants' => $c->participants
                ->where('id', '!=', $user->id)
                ->map(fn($u) => [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'username'     => $u->username,
                    'avatar'       => $u->avatar,
                    'avatar_color' => $u->avatar_color,
                ])->values(),
        ]);

    return response()->json($conversations);
});

    Route::get('/mobile/bubbles/{id}', function ($id) {
        $user   = auth()->user();
        $bubble = \App\Models\Bubble::with('user')->findOrFail($id);

        $activeMembers = fn() => $bubble->memberships()->wherePivot('status', 'active');

        $posts = $bubble->communityPosts()
            ->with(['user', 'likes' => fn($q) => $q->where('user_id', $user->id)])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'content'        => $p->content,
                'image'          => $p->image,
                'likes'          => $p->likes_count,
                'liked_by_me'    => $p->likes->isNotEmpty(),
                'comments_count' => $p->comments_count,
                'created_at'     => $p->created_at->diffForHumans(),
                'author'         => [
                    'id'           => $p->user->id,
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar'       => $p->user->avatar,
                    'avatar_color' => $p->user->avatar_color,
                ],
            ]);

        $membersList = $activeMembers()
            ->orderByPivot('joined_at', 'asc')
            ->limit(12)
            ->get()
            ->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'username'     => $u->username,
                'avatar'       => $u->avatar,
                'avatar_color' => $u->avatar_color,
            ]);

        return response()->json([
            'id'                    => $bubble->id,
            'label'                 => $bubble->label,
            'community_title'       => $bubble->community_title,
            'community_description' => $bubble->community_description,
            'community_tagline'     => $bubble->community_tagline,
            'community_cover_color' => $bubble->community_cover_color,
            'community_image'       => $bubble->community_image,
            'community_banner'      => $bubble->community_banner,
            'color'                 => $bubble->color,
            'members'               => $activeMembers()->count(),
            'is_member'             => $activeMembers()->where('community_user.user_id', $user->id)->exists(),
            'members_list'          => $membersList,
            'creator'               => $bubble->user ? [
                'id'       => $bubble->user->id,
                'name'     => $bubble->user->name,
                'username' => $bubble->user->username,
                'avatar'   => $bubble->user->avatar,
            ] : null,
            'posts' => $posts,
        ]);
    });

    Route::post('/mobile/bubbles/{id}/join', function ($id) {
        $user   = auth()->user();
        $bubble = \App\Models\Bubble::findOrFail($id);

        $membership = $bubble->memberships()->where('community_user.user_id', $user->id)->first();

        if ($membership) {
            if ($bubble->user_id === $user->id) {
                return response()->json(['message' => 'O criador não pode sair da comunidade.'], 422);
            }
            $bubble->memberships()->detach($user->id);
            $joined = false;
        } else {
            $bubble->memberships()->attach($user->id, [
                'role'      => 'member',
                'status'    => 'active',
                'joined_at' => now(),
            ]);
            $joined = true;
        }

        $count = $bubble->memberships()->wherePivot('status', 'active')->count();

        return response()->json(['joined' => $joined, 'members' => $count]);
    });

    Route::post('/mobile/community-posts/{id}/like', function ($id) {
        $user = auth()->user();
        $post = \App\Models\CommunityPost::findOrFail($id);

        $existing = $post->likes()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $user->id, 'type' => 'like']);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes' => $post->likes()->count(),
        ]);
    });

    Route::get('/mobile/community-posts/{id}/comments', function ($id) {
        $userId = auth()->id();
        $post = \App\Models\CommunityPost::findOrFail($id);

        $comments = $post->comments()
            ->whereNull('parent_comment_id')
            ->with([
                'user',
                'likes' => fn($q) => $q->where('user_id', $userId),
                'replies' => fn($q) => $q
                    ->with(['user', 'likes' => fn($lq) => $lq->where('user_id', $userId)])
                    ->withCount('likes'),
            ])
            ->withCount('likes')
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'content'     => $c->content,
                'created_at'  => $c->created_at->diffForHumans(),
                'likes'       => $c->likes_count,
                'liked_by_me' => $c->likes->isNotEmpty(),
                'replies'     => $c->replies->map(fn($r) => [
                    'id'          => $r->id,
                    'content'     => $r->content,
                    'created_at'  => $r->created_at->diffForHumans(),
                    'likes'       => $r->likes_count,
                    'liked_by_me' => $r->likes->isNotEmpty(),
                    'author'      => [
                        'id'           => $r->user->id,
                        'name'         => $r->user->name,
                        'username'     => $r->user->username,
                        'avatar'       => $r->user->avatar,
                        'avatar_color' => $r->user->avatar_color,
                    ],
                ]),
                'author'      => [
                    'id'           => $c->user->id,
                    'name'         => $c->user->name,
                    'username'     => $c->user->username,
                    'avatar'       => $c->user->avatar,
                    'avatar_color' => $c->user->avatar_color,
                ],
            ]);

        return response()->json($comments);
    });

    Route::post('/mobile/community-posts/{id}/comments', function (Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        $post = \App\Models\CommunityPost::findOrFail($id);
        $request->validate([
            'content'           => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|integer|exists:comments,id',
        ]);

        $comment = $post->comments()->create([
            'user_id'           => $user->id,
            'content'           => $request->content,
            'parent_comment_id' => $request->parent_comment_id,
        ]);

        return response()->json([
            'id'          => $comment->id,
            'content'     => $comment->content,
            'created_at'  => $comment->created_at->diffForHumans(),
            'likes'       => 0,
            'liked_by_me' => false,
            'replies'     => [],
            'author'      => [
                'id'           => $user->id,
                'name'         => $user->name,
                'username'     => $user->username,
                'avatar'       => $user->avatar,
                'avatar_color' => $user->avatar_color,
            ],
        ], 201);
    });

    Route::post('/mobile/posts', function (Illuminate\Http\Request $request) {
        $request->validate([
            'content' => 'nullable|string|max:5000',
            'image'   => 'nullable|url',
        ]);
        if (!$request->content && !$request->image) {
            return response()->json(['message' => 'Post vazio.'], 422);
        }

        $user = auth()->user();
        $post = $user->posts()->create([
            'content' => $request->content,
            'image'   => $request->input('image'),
        ]);

        return response()->json([
            'id'             => $post->id,
            'content'        => $post->content,
            'image'          => $post->image,
            'likes'          => 0,
            'liked_by_me'    => false,
            'comments_count' => 0,
            'created_at'     => $post->created_at->diffForHumans(),
            'author'         => [
                'id'           => $user->id,
                'name'         => $user->name,
                'username'     => $user->username,
                'avatar'       => $user->avatar,
                'avatar_color' => $user->avatar_color,
            ],
        ], 201);
    });

    Route::get('/mobile/friends', function () {
        $friends = \App\Models\Friend::friendsOf(auth()->id());
        return response()->json($friends);
    });

    Route::get('/mobile/users/{id}', function ($id) {
        $currentUser = auth()->user();
        $user = \App\Models\User::findOrFail($id);

        $friendsCount = $user->sentFriendRequests()->where('status', 'accepted')->count()
            + $user->receivedFriendRequests()->where('status', 'accepted')->count();

        $posts = $user->posts()
            ->withCount(['likes', 'comments'])
            ->with(['likes' => fn($q) => $q->where('user_id', $currentUser->id)])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'content'        => $p->content,
                'image'          => $p->image,
                'likes'          => $p->likes_count,
                'liked_by_me'    => $p->likes->isNotEmpty(),
                'comments_count' => $p->comments_count,
                'created_at'     => $p->created_at->diffForHumans(),
                'author'         => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'username'     => $user->username,
                    'avatar'       => $user->avatar,
                    'avatar_color' => $user->avatar_color,
                ],
            ]);

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'username'      => $user->username,
            'bio'           => $user->bio,
            'avatar'        => $user->avatar,
            'avatar_color'  => $user->avatar_color,
            'banner'        => $user->banner,
            'role'          => $user->role,
            'created_at'    => $user->created_at->format('M Y'),
            'posts_count'   => $user->posts()->count(),
            'friends_count' => $friendsCount,
            'posts'         => $posts,
        ]);
    });

    Route::get('/mobile/my-communities', function () {
        $user = auth()->user();
        $communities = $user->communities()
            ->withCount('memberships')
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'label'           => $b->label,
                'color'           => $b->color,
                'community_image' => $b->community_image,
                'members'         => $b->memberships_count,
            ]);
        return response()->json($communities);
    });

    Route::get('/mobile/posts/{id}/comments', function ($id) {
        $userId = auth()->id();
        $post = \App\Models\Post::findOrFail($id);

        $comments = $post->comments()
            ->whereNull('parent_comment_id')
            ->with([
                'user',
                'likes' => fn($q) => $q->where('user_id', $userId),
                'replies' => fn($q) => $q
                    ->with(['user', 'likes' => fn($lq) => $lq->where('user_id', $userId)])
                    ->withCount('likes'),
            ])
            ->withCount('likes')
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'content'     => $c->content,
                'created_at'  => $c->created_at->diffForHumans(),
                'likes'       => $c->likes_count,
                'liked_by_me' => $c->likes->isNotEmpty(),
                'replies'     => $c->replies->map(fn($r) => [
                    'id'          => $r->id,
                    'content'     => $r->content,
                    'created_at'  => $r->created_at->diffForHumans(),
                    'likes'       => $r->likes_count,
                    'liked_by_me' => $r->likes->isNotEmpty(),
                    'author'      => [
                        'id'           => $r->user->id,
                        'name'         => $r->user->name,
                        'username'     => $r->user->username,
                        'avatar'       => $r->user->avatar,
                        'avatar_color' => $r->user->avatar_color,
                    ],
                ]),
                'author'      => [
                    'id'           => $c->user->id,
                    'name'         => $c->user->name,
                    'username'     => $c->user->username,
                    'avatar'       => $c->user->avatar,
                    'avatar_color' => $c->user->avatar_color,
                ],
            ]);

        return response()->json($comments);
    });

    Route::post('/mobile/posts/{id}/comments', function (Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        $post = \App\Models\Post::findOrFail($id);
        $request->validate([
            'content'           => 'required|string|max:2000',
            'parent_comment_id' => 'nullable|integer|exists:comments,id',
        ]);

        $comment = $post->comments()->create([
            'user_id'           => $user->id,
            'content'           => $request->content,
            'parent_comment_id' => $request->parent_comment_id,
        ]);

        return response()->json([
            'id'          => $comment->id,
            'content'     => $comment->content,
            'created_at'  => $comment->created_at->diffForHumans(),
            'likes'       => 0,
            'liked_by_me' => false,
            'replies'     => [],
            'author'      => [
                'id'           => $user->id,
                'name'         => $user->name,
                'username'     => $user->username,
                'avatar'       => $user->avatar,
                'avatar_color' => $user->avatar_color,
            ],
        ], 201);
    });

    Route::post('/mobile/comments/{id}/like', function ($id) {
        $user = auth()->user();
        $comment = \App\Models\Comment::findOrFail($id);

        $existing = $comment->likes()->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $comment->likes()->create(['user_id' => $user->id, 'type' => 'like']);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes' => $comment->likes()->count(),
        ]);
    });

    Route::post('/mobile/conversations/with/{userId}', function ($userId) {
        $me = auth()->user();
        $other = \App\Models\User::findOrFail($userId);

        $conv = $me->conversations()
            ->where('type', 'direct')
            ->whereHas('participants', fn($q) => $q->where('users.id', $other->id))
            ->first();

        if (!$conv) {
            $conv = \App\Models\Conversation::create(['type' => 'direct']);
            $conv->participants()->attach([$me->id, $other->id]);
        }

        return response()->json(['id' => $conv->id]);
    });

    // Notifications
    Route::get('/mobile/notifications', function () {
        $user = auth()->user();
        $notifs = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'data'       => $n->data,
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]);
        return response()->json($notifs);
    });

    Route::patch('/mobile/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    });

    Route::patch('/mobile/notifications/{id}/read', function ($id) {
        $n = auth()->user()->notifications()->where('id', $id)->first();
        if ($n) $n->markAsRead();
        return response()->json(['ok' => true]);
    });

    Route::delete('/mobile/notifications/all', function () {
        auth()->user()->notifications()->delete();
        return response()->json(['ok' => true]);
    });

    Route::delete('/mobile/notifications/{id}', function ($id) {
        auth()->user()->notifications()->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    });

    Route::get('/mobile/search', function (Illuminate\Http\Request $request) {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => [], 'communities' => [], 'posts' => []]);
        }
        $like = '%' . $q . '%';

        $users = \App\Models\User::where('name', 'like', $like)
            ->orWhere('username', 'like', $like)
            ->limit(4)
            ->get()
            ->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'username'     => $u->username,
                'avatar'       => $u->avatar,
                'avatar_color' => $u->avatar_color,
            ]);

        $communities = \App\Models\Bubble::where('label', 'like', $like)
            ->orWhere('community_title', 'like', $like)
            ->limit(4)
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'label'           => $b->label,
                'community_title' => $b->community_title,
                'color'           => $b->color,
                'community_image' => $b->community_image,
                'members'         => $b->memberships()->wherePivot('status', 'active')->count(),
            ]);

        $posts = \App\Models\Post::where('content', 'like', $like)
            ->with('user')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'content'        => $p->content,
                'image'          => $p->image,
                'likes'          => 0,
                'liked_by_me'    => false,
                'comments_count' => 0,
                'created_at'     => $p->created_at->diffForHumans(),
                'author'         => [
                    'id'           => $p->user->id,
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar'       => $p->user->avatar,
                    'avatar_color' => $p->user->avatar_color,
                ],
            ]);

        return response()->json([
            'users'       => $users,
            'communities' => $communities,
            'posts'       => $posts,
        ]);
    });

});