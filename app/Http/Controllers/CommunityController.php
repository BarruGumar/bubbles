<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'created_at' => $p->created_at->diffForHumans(),
                'author'     => [
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                ],
                'isOwn' => auth()->check() && auth()->id() === $p->user_id,
            ]);

        return Inertia::render('Community/Show', [
            'community' => [
                'id'          => $bubble->id,
                'label'       => $bubble->label,
                'title'       => $bubble->community_title ?: $bubble->label,
                'description' => $bubble->community_description ?: 'Comunidade criada no bubbles.',
                'tagline'     => $bubble->community_tagline ?: 'Conecta, partilha e participa.',
                'color'       => $bubble->color ?? '#009ac7',
                'cover_color' => $bubble->community_cover_color ?: ($bubble->color ?? '#009ac7'),
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
        $request->validate(['content' => 'required|string|max:1000']);

        $bubble = Bubble::findOrFail($id);
        $bubble->communityPosts()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        return back();
    }

    public function destroy(int $id, CommunityPost $post): RedirectResponse
    {
        abort_if(auth()->id() !== $post->user_id, 403);
        $post->delete();
        return back();
    }
}
