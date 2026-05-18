<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunityPostRequest;
use App\Http\Requests\UpdateCommunitySettingsRequest;
use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\User;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    use StoresImages;

    public function show(int $id): Response
    {
        $bubble = Bubble::withCount('memberships')->findOrFail($id);
        $creator = User::find($bubble->user_id);

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

        $isOwn = auth()->check() && auth()->id() === $bubble->user_id;
        $authUser = auth()->user();
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
                'posts_count' => $bubble->communityPosts()->count(),
                'recent_posts_count' => $bubble->communityPosts()->where('created_at', '>=', now()->subDays(7))->count(),
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

    public function updateSettings(UpdateCommunitySettingsRequest $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        $data = $request->validated();

        if (isset($data['color'])) {
            $data['community_cover_color'] = $data['color'];
        }

        $bubble->update($data);

        return back()->with('status', 'community-updated');
    }

    public function deleteCommunity(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        $bubble->delete();

        return redirect()->route('bubbles');
    }

    public function join(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        $bubble->memberships()->syncWithoutDetaching([auth()->id()]);

        return back();
    }

    public function leave(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);

        if ($bubble->user_id === auth()->id()) {
            return back();
        }

        $bubble->memberships()->detach(auth()->id());

        return back();
    }

    public function store(StoreCommunityPostRequest $request, int $id): RedirectResponse
    {
        $user   = $request->user();
        $bubble = Bubble::findOrFail($id);

        abort_if($user->isBanned(), 403, 'A tua conta foi banida.');
        abort_if($user->isSuspended(), 403, 'A tua conta está suspensa.');
        abort_if($user->isGloballyMuted(), 403, 'Estás em silêncio global.');
        abort_if($user->isBannedFromCommunity($bubble), 403, 'Estás banido desta comunidade.');
        abort_if($user->isMutedInCommunity($bubble), 403, 'Estás em silêncio nesta comunidade.');

        $imageUrl = null;
        $imagePid = null;
        $videoUrl = null;
        $videoPid = null;

        if ($request->hasFile('image')) {
            ['url' => $imageUrl, 'public_id' => $imagePid] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/posts',
                ['transformation' => ['width' => 1200, 'height' => 800, 'crop' => 'limit', 'fetch_format' => 'auto', 'quality' => 'auto']]
            );
        }

        if ($request->hasFile('video')) {
            ['url' => $videoUrl, 'public_id' => $videoPid] = $this->storeVideoWithMeta(
                $request->file('video'),
                'bubbles/posts'
            );
        }

        $bubble->communityPosts()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'image' => $imageUrl,
            'image_public_id' => $imagePid,
            'video' => $videoUrl,
            'video_public_id' => $videoPid,
        ]);

        return back();
    }

    public function updatePost(Request $request, int $id, CommunityPost $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate(['content' => 'required|string|min:1|max:1000']);
        $post->update(['content' => $data['content']]);

        return response()->json(['content' => $post->content]);
    }

    public function destroy(int $id, CommunityPost $post): RedirectResponse
    {
        Gate::authorize('delete', $post);
        $this->deleteCloudinaryImage($post->image_public_id);
        $this->deleteCloudinaryVideo($post->video_public_id);
        $post->delete();

        return back();
    }

    public function uploadImage(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);
        $request->validate(['image' => 'required|image|max:2048']);

        $this->deleteCloudinaryImage($bubble->community_image_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta($request->file('image'), 'bubbles/communities', [
            'public_id' => 'community_img_'.$id,
            'overwrite' => true,
            'transformation' => ['width' => 300, 'height' => 300, 'crop' => 'fill', 'fetch_format' => 'auto', 'quality' => 'auto'],
        ]);

        $bubble->update(['community_image' => $url, 'community_image_public_id' => $pid]);

        return back();
    }

    public function uploadBanner(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);
        $request->validate(['banner' => 'required|image|max:4096']);

        $this->deleteCloudinaryImage($bubble->community_banner_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta($request->file('banner'), 'bubbles/communities', [
            'public_id' => 'community_banner_'.$id,
            'overwrite' => true,
            'transformation' => ['width' => 1400, 'height' => 500, 'crop' => 'fill', 'fetch_format' => 'auto', 'quality' => 'auto'],
        ]);

        $bubble->update(['community_banner' => $url, 'community_banner_public_id' => $pid]);

        return back();
    }
}
