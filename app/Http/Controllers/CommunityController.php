<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\User;
use App\Support\StoresImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    use StoresImages;


    public function show(int $id): Response
    {
        $bubble  = Bubble::withCount('memberships')->findOrFail($id);
        $creator = User::find($bubble->user_id);

        $userId = auth()->id();

        $posts = $bubble->communityPosts()
            ->withCount('likes')
            ->with([
                'user',
                'likes'    => fn ($q) => $q->where('user_id', $userId ?? 0),
                'comments' => fn ($q) => $q->with('user')->orderBy('created_at'),
            ])
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'content'     => $p->content,
                'image'       => $p->image,
                'created_at'  => $p->created_at->diffForHumans(),
                'author'      => [
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                    'avatar'       => $p->user->avatar,
                ],
                'isOwn'       => auth()->check() && auth()->id() === $p->user_id,
                'isCreator'   => $p->user_id === $bubble->user_id,
                'likes_count' => $p->likes_count,
                'is_liked'    => $p->likes->isNotEmpty(),
                'comments'    => $p->comments->map(fn ($c) => [
                    'id'         => $c->id,
                    'content'    => $c->content,
                    'created_at' => $c->created_at->diffForHumans(),
                    'is_own'     => $userId && $c->user_id === $userId,
                    'author'     => [
                        'id'           => $c->user->id,
                        'name'         => $c->user->name,
                        'username'     => $c->user->username,
                        'avatar'       => $c->user->avatar,
                        'avatar_color' => $c->user->avatar_color ?? '#009ac7',
                    ],
                ])->values(),
            ]);

        $memberAvatars = $bubble->memberships()
            ->orderBy('community_user.created_at', 'asc')
            ->take(6)
            ->get()
            ->map(fn ($u) => [
                'name'         => $u->name,
                'username'     => $u->username,
                'avatar'       => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
            ])
            ->values()
            ->toArray();

        return Inertia::render('Community/Show', [
            'isOwn'    => auth()->check() && auth()->id() === $bubble->user_id,
            'isMember' => auth()->check() && $bubble->memberships()->where('user_id', auth()->id())->exists(),
            'community' => [
                'id'             => $bubble->id,
                'label'          => $bubble->label,
                'title'          => $bubble->community_title ?: $bubble->label,
                'description'    => $bubble->community_description ?: 'Comunidade criada no bubbles.',
                'tagline'        => $bubble->community_tagline ?: 'Conecta, partilha e participa.',
                'color'          => $bubble->color ?? '#009ac7',
                'cover_color'    => $bubble->community_cover_color ?: ($bubble->color ?? '#009ac7'),
                'image'          => $bubble->community_image,
                'banner'         => $bubble->community_banner,
                'guidelines'     => $bubble->community_guidelines ?: [
                    'Respeita os outros membros.',
                    'Evita spam e conteúdos repetidos.',
                    'Partilha conteúdo relevante para o tema.',
                ],
                'members'        => $bubble->memberships_count,
                'member_avatars' => $memberAvatars,
                'creator'        => $creator ? [
                    'id'           => $creator->id,
                    'name'         => $creator->name,
                    'username'     => $creator->username,
                    'avatar'       => $creator->avatar,
                    'avatar_color' => $creator->avatar_color ?? '#009ac7',
                ] : null,
            ],
            'posts' => $posts,
        ]);
    }

    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        abort_if(auth()->id() !== $bubble->user_id, 403);

        $data = $request->validate([
            'label'                  => ['required', 'string', 'max:120'],
            'community_title'        => ['nullable', 'string', 'max:120'],
            'community_tagline'      => ['nullable', 'string', 'max:160'],
            'community_description'  => ['nullable', 'string', 'max:1000'],
            'color'                  => ['nullable', 'string', 'max:40'],
            'community_guidelines'   => ['nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        if (isset($data['color'])) {
            $data['community_cover_color'] = $data['color'];
        }

        $bubble->update($data);

        return back()->with('status', 'community-updated');
    }

    public function deleteCommunity(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        abort_if(auth()->id() !== $bubble->user_id, 403);

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
        $bubble->memberships()->detach(auth()->id());

        return back();
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'image'   => 'nullable|image|max:4096',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeImage($request->file('image'), 'bubbles/posts', [
                'transformation' => ['width'=>1200,'height'=>800,'crop'=>'limit','fetch_format'=>'auto','quality'=>'auto'],
            ]);
        }

        $bubble = Bubble::findOrFail($id);
        $bubble->communityPosts()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'image'   => $imageUrl,
        ]);

        return back();
    }

    public function destroy(int $id, CommunityPost $post): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        $isCommunityCreator = auth()->id() === $bubble->user_id;
        abort_if(auth()->id() !== $post->user_id && !$isCommunityCreator, 403);
        $post->delete();
        return back();
    }

    public function uploadImage(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        abort_if(auth()->id() !== $bubble->user_id, 403);
        $request->validate(['image' => 'required|image|max:2048']);
        $url = $this->storeImage($request->file('image'), 'bubbles/communities', [
            'public_id' => 'community_img_' . $id, 'overwrite' => true,
            'transformation' => ['width'=>300,'height'=>300,'crop'=>'fill','fetch_format'=>'auto','quality'=>'auto'],
        ]);
        $bubble->update(['community_image' => $url]);
        return back();
    }

    public function uploadBanner(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        abort_if(auth()->id() !== $bubble->user_id, 403);
        $request->validate(['banner' => 'required|image|max:4096']);
        $url = $this->storeImage($request->file('banner'), 'bubbles/communities', [
            'public_id' => 'community_banner_' . $id, 'overwrite' => true,
            'transformation' => ['width'=>1400,'height'=>500,'crop'=>'fill','fetch_format'=>'auto','quality'=>'auto'],
        ]);
        $bubble->update(['community_banner' => $url]);
        return back();
    }
}