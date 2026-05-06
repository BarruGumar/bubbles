<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function show(int $id): Response
    {
        $bubble = Bubble::findOrFail($id);

        $posts = $bubble->communityPosts()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'content'    => $p->content,
                'image'      => $p->image,
                'created_at' => $p->created_at->diffForHumans(),
                'author'     => [
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                    'avatar'       => $p->user->avatar,
                ],
                'isOwn' => auth()->check() && auth()->id() === $p->user_id,
            ]);

        return Inertia::render('Community/Show', [
            'isOwn'     => auth()->check() && auth()->id() === $bubble->user_id,
            'community' => [
                'id'          => $bubble->id,
                'label'       => $bubble->label,
                'title'       => $bubble->community_title ?: $bubble->label,
                'description' => $bubble->community_description ?: 'Comunidade criada no bubbles.',
                'tagline'     => $bubble->community_tagline ?: 'Conecta, partilha e participa.',
                'color'       => $bubble->color ?? '#009ac7',
                'cover_color' => $bubble->community_cover_color ?: ($bubble->color ?? '#009ac7'),
                'image'       => $bubble->community_image,
                'banner'      => $bubble->community_banner,
                'guidelines'  => $bubble->community_guidelines ?: [
                    'Respeita os outros membros.',
                    'Evita spam e conteúdos repetidos.',
                    'Partilha conteúdo relevante para o tema.',
                ],
                'members' => $bubble->members ?? 0,
            ],
            'posts' => $posts,
        ]);
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
        abort_if(auth()->id() !== $post->user_id, 403);
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

    private function storeImage($file, string $folder, array $cloudinaryOptions = []): string
    {
        $key = env('CLOUDINARY_API_KEY', '');
        if (!empty($key) && $key !== 'API_KEY') {
            return Cloudinary::upload($file->getRealPath(), array_merge(
                ['folder' => $folder, 'fetch_format' => 'auto', 'quality' => 'auto'],
                $cloudinaryOptions
            ))->getSecurePath();
        }

        $path = $file->store($folder, 'public');
        return '/storage/' . $path;
    }
}
