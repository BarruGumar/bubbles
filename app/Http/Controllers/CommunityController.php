<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function show(int $id): Response
    {
        $bubble = Bubble::withCount([
            'memberships',
            'communityPosts',
            'communityPosts as recent_posts_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays(7)),
        ])->findOrFail($id);
        $creator = User::find($bubble->user_id);

        $authUser = auth()->user();
        if ($authUser && $authUser->isBannedFromCommunity($bubble)) {
            abort(403, 'Estás banido desta comunidade.');
        }

        $userId = auth()->id();

        $paginated = $bubble->communityPosts()
            ->withCount('likes')
            ->with([
                'user',
                'likes' => fn ($q) => $q->where('user_id', $userId ?? 0),
                'comments' => fn ($q) => $q->with('user')->orderBy('created_at')->limit(5),
            ])
            ->latest()
            ->cursorPaginate(12);

        $posts = $paginated->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'content' => $p->content,
            'image' => $p->image,
            'video' => $p->video,
            'created_at' => $p->created_at->diffForHumans(),
            'author' => [
                'name' => $p->user->name,
                'username' => $p->user->username,
                'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                'avatar' => $p->user->avatar,
                'role' => $p->user->role,
            ],
            'isOwn' => auth()->check() && auth()->id() === $p->user_id,
            'isCreator' => $p->user_id === $bubble->user_id,
            'likes_count' => $p->likes_count,
            'is_liked' => $p->likes->isNotEmpty(),
            'user_reaction' => $p->likes->first()?->type ?? null,
            'comments' => $p->comments->map(fn ($c) => [
                'id' => $c->id,
                'content' => $c->content,
                'created_at' => $c->created_at->diffForHumans(),
                'is_own' => $userId && $c->user_id === $userId,
                'author' => [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'username' => $c->user->username,
                    'avatar' => $c->user->avatar,
                    'avatar_color' => $c->user->avatar_color ?? '#009ac7',
                ],
            ])->values(),
        ])->values();

        $memberAvatars = $bubble->memberships()
            ->orderBy('community_user.created_at', 'asc')
            ->take(6)
            ->get()
            ->map(fn ($u) => [
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
            ])
            ->values()
            ->toArray();

        $isOwn      = auth()->check() && auth()->id() === $bubble->user_id;
        $authUser   = auth()->user();
        $canModerate = $authUser && $authUser->canModerateCommunity($bubble);
        $canManage   = $authUser && $authUser->canManageCommunity($bubble);

        return Inertia::render('Community/Show', [
            'isOwn'       => $isOwn,
            'canModerate' => $canModerate,
            'canManage'   => $canManage,
            'isMember' => $isOwn || (auth()->check() && $bubble->memberships()->where('user_id', auth()->id())->exists()),
            'community' => [
                'id' => $bubble->id,
                'label' => $bubble->label,
                'title' => $bubble->community_title ?: $bubble->label,
                'description' => $bubble->community_description ?: 'Comunidade criada no bubbles.',
                'tagline' => $bubble->community_tagline ?: 'Conecta, partilha e participa.',
                'color' => $bubble->color ?? '#009ac7',
                'cover_color' => $bubble->community_cover_color ?: ($bubble->color ?? '#009ac7'),
                'image' => $bubble->community_image,
                'banner' => $bubble->community_banner,
                'guidelines' => $bubble->community_guidelines ?: [
                    'Respeita os outros membros.',
                    'Evita spam e conteúdos repetidos.',
                    'Partilha conteúdo relevante para o tema.',
                ],
                'members' => $bubble->memberships_count,
                'posts_count' => $bubble->community_posts_count,
                'recent_posts_count' => $bubble->recent_posts_count,
                'member_avatars' => $memberAvatars,
                'creator' => $creator ? [
                    'id' => $creator->id,
                    'name' => $creator->name,
                    'username' => $creator->username,
                    'avatar' => $creator->avatar,
                    'avatar_color' => $creator->avatar_color ?? '#009ac7',
                ] : null,
            ],
            'posts' => $posts,
            'nextCursor' => $paginated->nextCursor()?->encode(),
            'hasMorePosts' => $paginated->hasMorePages(),
        ]);
    }

    public function join(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        $bubble->memberships()->syncWithoutDetaching([auth()->id()]);

        Cache::forget('user:' . auth()->id() . ':community_ids');

        AuditLogger::log('community.joined', 'community', $bubble, [], $bubble->id);

        return back();
    }

    public function leave(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);

        if ($bubble->user_id === auth()->id()) {
            return back();
        }

        $bubble->memberships()->detach(auth()->id());

        Cache::forget('user:' . auth()->id() . ':community_ids');

        AuditLogger::log('community.left', 'community', $bubble, [], $bubble->id);

        return back();
    }

    public function userCommunities(): Response
    {
        $user = auth()->user();

        $communities = $user->communities()
            ->withCount('memberships')
            ->get()
            ->map(fn ($b) => [
                'id'      => $b->id,
                'label'   => $b->label,
                'title'   => $b->community_title ?: $b->label,
                'color'   => $b->color ?? '#009ac7',
                'image'   => $b->community_image,
                'members' => $b->memberships_count,
            ])
            ->values();

        return Inertia::render('Communities/Index', [
            'communities' => $communities,
        ]);
    }
}
