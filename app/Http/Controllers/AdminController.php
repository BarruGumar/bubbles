<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'posts' => Post::withTrashed()->count(),
                'communities' => Bubble::count(),
                'reports' => Report::where('status', 'pending')->count(),
            ],
            'recentReports' => Report::with('reporter')
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'type' => class_basename($r->reportable_type),
                    'reporter_name' => $r->reporter->name ?? '?',
                    'created_at' => $r->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function users(Request $request): Response
    {
        $q = $request->get('q');

        $users = User::when($q, fn ($query) => $query->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'role' => $u->role,
                'avatar' => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
                'posts_count' => $u->posts()->withTrashed()->count(),
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'query' => $q,
        ]);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => 'required|in:user,moderator,admin,suspended',
        ]);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Não podes alterar o teu próprio papel.');
        }

        $user->update(['role' => $data['role']]);

        return back()->with('status', 'Papel do utilizador atualizado.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Não podes apagar a tua própria conta.');
        }

        $user->delete();

        return back()->with('status', 'Utilizador eliminado.');
    }

    public function posts(Request $request): Response
    {
        $q = $request->get('q');

        $posts = Post::withTrashed()
            ->with('user')
            ->when($q, fn ($query) => $query->where('content', 'like', "%$q%"))
            ->latest()
            ->paginate(20)
            ->through(fn ($p) => [
                'id' => $p->id,
                'content' => mb_substr($p->content, 0, 120).(mb_strlen($p->content) > 120 ? '…' : ''),
                'deleted' => (bool) $p->deleted_at,
                'author' => $p->user ? ['name' => $p->user->name, 'username' => $p->user->username] : null,
                'created_at' => $p->created_at->format('d/m/Y H:i'),
                'deleted_at' => $p->deleted_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Posts', [
            'posts' => $posts,
            'query' => $q,
        ]);
    }

    public function destroyPost(Post $post): RedirectResponse
    {
        $post->forceDelete();

        return back()->with('status', 'Post eliminado permanentemente.');
    }

    public function restorePost(int $id): RedirectResponse
    {
        Post::withTrashed()->findOrFail($id)->restore();

        return back()->with('status', 'Post restaurado.');
    }

    public function communities(Request $request): Response
    {
        $q = $request->get('q');

        $communities = Bubble::when($q, fn ($query) => $query->where('community_title', 'like', "%$q%")->orWhere('label', 'like', "%$q%"))
            ->withCount('memberships')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($b) => [
                'id' => $b->id,
                'label' => $b->label,
                'title' => $b->community_title ?: $b->label,
                'color' => $b->color ?? '#009ac7',
                'members' => $b->memberships_count,
                'posts_count' => $b->communityPosts()->count(),
                'created_at' => $b->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Communities', [
            'communities' => $communities,
            'query' => $q,
        ]);
    }

    public function destroyCommunity(Bubble $bubble): RedirectResponse
    {
        $bubble->delete();

        return back()->with('status', 'Comunidade eliminada.');
    }

    public function reports(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $reports = Report::with(['reporter', 'reportable', 'reportable.user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->through(fn ($r) => [
                'id' => $r->id,
                'reason' => $r->reason,
                'status' => $r->status,
                'admin_note' => $r->admin_note,
                'type' => class_basename($r->reportable_type),
                'reporter_name' => $r->reporter?->name ?? '–',
                'reportable_content' => $r->reportable?->content ?? null,
                'reportable_author' => $r->reportable?->user?->name ?? null,
                'created_at' => $r->created_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/Reports', [
            'reports' => $reports,
            'statusFilter' => $status,
        ]);
    }

    public function resolveReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => 'resolved', 'admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia resolvida.');
    }

    public function dismissReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => 'dismissed', 'admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia dispensada.');
    }
}
