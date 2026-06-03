<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Friend;
use App\Models\Post;
use App\Models\UserBlock;
use App\Support\FormatsCommentData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    use FormatsCommentData;
    public function index(Request $request): Response
    {
        return Inertia::render('Feed/Index', $this->feedData($request));
    }

    public function home(Request $request): Response
    {
        return Inertia::render('Bubbles', $this->feedData($request));
    }

    private function feedData(Request $request): array
    {
        $user = $request->user();
        $authId = $user->id;

        $cursor = $request->query('cursor')
            ? Carbon::createFromTimestamp((int) $request->query('cursor'))
            : null;

        $friendIds = Cache::remember("user:{$authId}:friend_ids", now()->addMinutes(5), function () use ($authId) {
            return Friend::where('status', 'accepted')
                ->where(fn ($q) => $q->where('user_id', $authId)->orWhere('friend_id', $authId))
                ->select(['user_id', 'friend_id'])
                ->get()
                ->map(fn ($f) => $f->user_id === $authId ? $f->friend_id : $f->user_id)
                ->toArray();
        });

        $communityIds = Cache::remember("user:{$authId}:community_ids", now()->addMinutes(5), function () use ($user, $authId) {
            return $user->communities()->pluck('bubbles.id')
                ->merge(Bubble::where('user_id', $authId)->pluck('id'))
                ->unique()
                ->toArray();
        });

        $blockedIds = UserBlock::mutualIds($authId);

        $withLikes = fn ($q) => $q->where('user_id', $authId);

        $withComments = fn ($q) => $q
            ->with([
                'user',
                'likes' => $withLikes,
                'replies' => fn ($rq) => $rq->with(['user', 'likes' => $withLikes]),
            ])
            ->whereNull('parent_comment_id')
            ->orderBy('created_at')
            ->limit(5);

        $friendPosts = Post::with(['user', 'likes' => $withLikes, 'comments' => $withComments])
            ->withCount('likes')
            ->whereIn('user_id', $friendIds)
            ->when($blockedIds, fn ($q) => $q->whereNotIn('user_id', $blockedIds))
            ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($p) => $this->mapPost($p, $authId));

        $communityPosts = CommunityPost::with(['user', 'bubble', 'likes' => $withLikes, 'comments' => $withComments])
            ->withCount('likes')
            ->whereIn('bubble_id', $communityIds)
            ->when($blockedIds, fn ($q) => $q->whereNotIn('user_id', $blockedIds))
            ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($p) => $this->mapCommunityPost($p, $authId));

        $recentPosts = collect([]);
        if ($friendPosts->isEmpty() && $communityPosts->isEmpty()) {
            $recentPosts = Post::with(['user', 'likes' => $withLikes, 'comments' => $withComments])
                ->withCount('likes')
                ->when($blockedIds, fn ($q) => $q->whereNotIn('user_id', $blockedIds))
                ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($p) => $this->mapPost($p, $authId));
        }

        $merged = $friendPosts->concat($communityPosts)->concat($recentPosts)
            ->sortByDesc('_ts')
            ->take(20);

        $hasMore    = $merged->count() === 20;
        $nextCursor = $hasMore ? $merged->last()['_ts'] : null;

        $feed = $merged
            ->map(fn ($item) => collect($item)->except('_ts')->all())
            ->values();

        return [
            'feed'            => $feed,
            'hasFriends'      => count($friendIds) > 0,
            'hasCommunities'  => count($communityIds) > 0,
            'hasMore'         => $hasMore,
            'nextCursor'      => $nextCursor,
        ];
    }

    private function mapPost(Post $p, int $authId): array
    {
        return [
            '_type' => 'post',
            '_ts' => $p->created_at->timestamp,
            'id' => $p->id,
            'content' => $p->content,
            'image' => $p->image,
            'video' => $p->video,
            'created_at' => $p->created_at->diffForHumans(),
            'likes_count' => $p->likes_count,
            'is_liked' => $p->likes->isNotEmpty(),
            'user_reaction' => $p->likes->first()?->type ?? null,
            'can_edit' => $p->user_id === $authId,
            'can_delete' => $p->user_id === $authId,
            'edit_route' => route('posts.update', $p->id),
            'delete_route' => route('posts.destroy', $p->id),
            'like_route' => route('posts.like', $p->id),
            'comment_route' => route('posts.comments.store', $p->id),
            'author' => $this->mapAuthor($p->user),
            'comments' => $p->comments->map(fn ($c) => $this->mapComment($c, $authId))->values(),
        ];
    }

    private function mapCommunityPost(CommunityPost $p, int $authId): array
    {
        $isCreatorOfCommunity = $p->bubble && $p->bubble->user_id === $authId;

        return [
            '_type' => 'community_post',
            '_ts' => $p->created_at->timestamp,
            'id' => $p->id,
            'content' => $p->content,
            'image' => $p->image,
            'video' => $p->video,
            'created_at' => $p->created_at->diffForHumans(),
            'likes_count' => $p->likes_count,
            'is_liked' => $p->likes->isNotEmpty(),
            'user_reaction' => $p->likes->first()?->type ?? null,
            'can_edit' => $p->user_id === $authId,
            'can_delete' => $p->user_id === $authId || $isCreatorOfCommunity,
            'edit_route' => route('community.posts.update', [$p->bubble_id, $p->id]),
            'delete_route' => route('community.posts.destroy', [$p->bubble_id, $p->id]),
            'like_route' => route('community-posts.like', $p->id),
            'comment_route' => route('community-posts.comments.store', $p->id),
            'community' => $p->bubble ? [
                'id' => $p->bubble->id,
                'label' => $p->bubble->label,
                'title' => $p->bubble->community_title ?: $p->bubble->label,
                'color' => $p->bubble->color ?? '#009ac7',
                'image' => $p->bubble->community_image,
            ] : null,
            'author' => $this->mapAuthor($p->user),
            'comments' => $p->comments->map(fn ($c) => $this->mapComment($c, $authId))->values(),
        ];
    }

    private function mapAuthor($user, bool $includeId = true): array
    {
        $data = [
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'avatar_color' => $user->avatar_color ?? '#009ac7',
            'role' => $user->role,
        ];

        if ($includeId) {
            $data = array_merge(['id' => $user->id], $data);
        }

        return $data;
    }
}
