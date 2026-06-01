<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function posts(Request $request): Response
    {
        $q = $request->get('q');

        $posts = Post::withTrashed()
            ->with('user')
            ->when($q, fn ($query) => $query->where('content', 'like', "%$q%"))
            ->latest()
            ->paginate(20)
            ->through(fn ($p) => [
                'id'         => $p->id,
                'content'    => mb_substr($p->content, 0, 120) . (mb_strlen($p->content) > 120 ? '…' : ''),
                'deleted'    => (bool) $p->deleted_at,
                'author'     => $p->user ? ['name' => $p->user->name, 'username' => $p->user->username] : null,
                'created_at' => $p->created_at->format('d/m/Y H:i'),
                'deleted_at' => $p->deleted_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Posts', ['posts' => $posts, 'query' => $q]);
    }

    public function destroyPost(int $id): RedirectResponse
    {
        $post = Post::withTrashed()->find($id);

        if (! $post) {
            return back()->with('error', 'Este post já foi eliminado permanentemente.');
        }

        AuditLogger::log('post.force_delete', 'content', $post, [
            'content_preview' => mb_substr($post->content ?? '', 0, 200),
            'author_id'       => $post->user_id,
        ]);

        $post->forceDelete();

        return back()->with('status', 'Post eliminado permanentemente.');
    }

    public function restorePost(int $id): RedirectResponse
    {
        $post = Post::withTrashed()->findOrFail($id);

        AuditLogger::log('post.restore', 'content', $post, ['author_id' => $post->user_id]);
        $post->restore();

        return back()->with('status', 'Post restaurado.');
    }

    public function communityPosts(Request $request): Response
    {
        $q = $request->get('q');

        $posts = CommunityPost::withTrashed()
            ->with(['user', 'bubble'])
            ->when($q, fn ($query) => $query->where('content', 'like', "%$q%"))
            ->latest()
            ->paginate(20)
            ->through(fn ($p) => [
                'id'         => $p->id,
                'content'    => mb_substr($p->content ?? '', 0, 120) . (mb_strlen($p->content ?? '') > 120 ? '…' : ''),
                'deleted'    => (bool) $p->deleted_at,
                'author'     => $p->user ? ['name' => $p->user->name, 'username' => $p->user->username] : null,
                'community'  => $p->bubble ? ['id' => $p->bubble->id, 'label' => $p->bubble->label] : null,
                'created_at' => $p->created_at->format('d/m/Y H:i'),
                'deleted_at' => $p->deleted_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/CommunityPosts', ['posts' => $posts, 'query' => $q]);
    }

    public function destroyCommunityPost(int $id): RedirectResponse
    {
        $post = CommunityPost::withTrashed()->find($id);

        if (! $post) {
            return back()->with('error', 'Este post já foi eliminado permanentemente.');
        }

        AuditLogger::log('community_post.force_delete', 'content', $post, [
            'content_preview' => mb_substr($post->content ?? '', 0, 200),
            'author_id'       => $post->user_id,
        ]);

        $post->forceDelete();

        return back()->with('status', 'Post de comunidade eliminado permanentemente.');
    }

    public function restoreCommunityPost(int $id): RedirectResponse
    {
        $post = CommunityPost::withTrashed()->findOrFail($id);

        AuditLogger::log('community_post.restore', 'content', $post, ['author_id' => $post->user_id]);
        $post->restore();

        return back()->with('status', 'Post de comunidade restaurado.');
    }

    public function communities(Request $request): Response
    {
        $q = $request->get('q');

        $communities = Bubble::when($q, fn ($query) => $query->where('community_title', 'like', "%$q%")->orWhere('label', 'like', "%$q%"))
            ->withCount(['memberships', 'communityPosts'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($b) => [
                'id'          => $b->id,
                'label'       => $b->label,
                'title'       => $b->community_title ?: $b->label,
                'color'       => $b->color ?? '#009ac7',
                'members'     => $b->memberships_count,
                'posts_count' => $b->community_posts_count,
                'created_at'  => $b->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Communities', ['communities' => $communities, 'query' => $q]);
    }

    public function destroyCommunity(Bubble $bubble): RedirectResponse
    {
        AuditLogger::log('community.delete', 'admin', $bubble, ['label' => $bubble->label]);
        $bubble->delete();

        return back()->with('status', 'Comunidade eliminada.');
    }
}
