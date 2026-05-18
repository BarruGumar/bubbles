<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bubble;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\UserPunishment;
use App\Services\AuditLogger;
use App\Services\PunishmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct(private PunishmentService $punishments) {}

    // ── Dashboard ─────────────────────────────────────────────────

    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users'       => User::count(),
                'posts'       => Post::withTrashed()->count(),
                'communities' => Bubble::count(),
                'reports'     => Report::where('status', 'pending')->count(),
                'punishments' => UserPunishment::active()->count(),
            ],
            'recentReports' => Report::with('reporter')
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($r) => [
                    'id'            => $r->id,
                    'reason'        => $r->reason,
                    'type'          => class_basename($r->reportable_type),
                    'reporter_name' => $r->reporter->name ?? '?',
                    'created_at'    => $r->created_at->diffForHumans(),
                ]),
        ]);
    }

    // ── Users ─────────────────────────────────────────────────────

    public function users(Request $request): Response
    {
        $q = $request->get('q');

        $users = User::when($q, fn ($query) => $query->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%"))
            ->withCount(['punishments as active_punishments_count' => fn ($q) => $q->active()])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($u) => [
                'id'                      => $u->id,
                'name'                    => $u->name,
                'username'                => $u->username,
                'email'                   => $u->email,
                'role'                    => $u->role,
                'avatar'                  => $u->avatar,
                'avatar_color'            => $u->avatar_color ?? '#009ac7',
                'posts_count'             => $u->posts()->withTrashed()->count(),
                'active_punishments_count' => $u->active_punishments_count,
                'is_banned'               => $u->isBanned(),
                'is_suspended'            => $u->isSuspended(),
                'is_muted'                => $u->isGloballyMuted(),
                'created_at'              => $u->created_at->format('d/m/Y'),
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

        $old = $user->role;
        $user->update(['role' => $data['role']]);

        AuditLogger::log(
            'user.role.update',
            'admin',
            $user,
            ['old_role' => $old, 'new_role' => $data['role']]
        );

        return back()->with('status', 'Papel do utilizador atualizado.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Não podes apagar a tua própria conta.');
        }

        AuditLogger::log('user.delete', 'admin', $user, ['username' => $user->username]);

        $user->delete();

        return back()->with('status', 'Utilizador eliminado.');
    }

    // ── Posts ─────────────────────────────────────────────────────

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
                'content'    => mb_substr($p->content, 0, 120).(mb_strlen($p->content) > 120 ? '…' : ''),
                'deleted'    => (bool) $p->deleted_at,
                'author'     => $p->user ? ['name' => $p->user->name, 'username' => $p->user->username] : null,
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
        AuditLogger::log(
            'post.force_delete',
            'content',
            $post,
            ['content_preview' => mb_substr($post->content ?? '', 0, 200), 'author_id' => $post->user_id]
        );

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

    // ── Communities ───────────────────────────────────────────────

    public function communities(Request $request): Response
    {
        $q = $request->get('q');

        $communities = Bubble::when($q, fn ($query) => $query->where('community_title', 'like', "%$q%")->orWhere('label', 'like', "%$q%"))
            ->withCount('memberships')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($b) => [
                'id'          => $b->id,
                'label'       => $b->label,
                'title'       => $b->community_title ?: $b->label,
                'color'       => $b->color ?? '#009ac7',
                'members'     => $b->memberships_count,
                'posts_count' => $b->communityPosts()->count(),
                'created_at'  => $b->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Communities', [
            'communities' => $communities,
            'query'       => $q,
        ]);
    }

    public function destroyCommunity(Bubble $bubble): RedirectResponse
    {
        AuditLogger::log('community.delete', 'admin', $bubble, ['label' => $bubble->label]);

        $bubble->delete();

        return back()->with('status', 'Comunidade eliminada.');
    }

    // ── Reports ───────────────────────────────────────────────────

    public function reports(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $reports = Report::with(['reporter', 'reportable', 'reportable.user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->through(fn ($r) => [
                'id'                 => $r->id,
                'reason'             => $r->reason,
                'status'             => $r->status,
                'admin_note'         => $r->admin_note,
                'type'               => class_basename($r->reportable_type),
                'reporter_name'      => $r->reporter?->name ?? '–',
                'reportable_content' => $r->reportable?->content ?? null,
                'reportable_author'  => $r->reportable?->user?->name ?? null,
                'reportable_user_id' => $r->reportable?->user_id ?? null,
                'created_at'         => $r->created_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/Reports', [
            'reports'      => $reports,
            'statusFilter' => $status,
        ]);
    }

    public function resolveReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => 'resolved', 'admin_note' => $data['admin_note'] ?? null]);

        AuditLogger::log('report.resolve', 'moderation', $report, ['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia resolvida.');
    }

    public function dismissReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => 'dismissed', 'admin_note' => $data['admin_note'] ?? null]);

        AuditLogger::log('report.dismiss', 'moderation', $report, ['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia dispensada.');
    }

    // ── Punishments ───────────────────────────────────────────────

    public function punishments(Request $request): Response
    {
        $q      = $request->get('q');
        $type   = $request->get('type');
        $status = $request->get('status', 'active'); // active | expired | revoked | all

        $list = UserPunishment::with(['user', 'issuedBy', 'revokedBy'])
            ->when($q, fn ($query) => $query->whereHas(
                'user',
                fn ($u) => $u->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%")
            ))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($status === 'expired', fn ($query) => $query->whereNull('revoked_at')
                ->whereNotNull('ends_at')->where('ends_at', '<', now()))
            ->latest()
            ->paginate(25)
            ->through(fn ($p) => [
                'id'             => $p->id,
                'type'           => $p->type,
                'reason'         => $p->reason,
                'notes'          => $p->notes,
                'starts_at'      => $p->starts_at->format('d/m/Y H:i'),
                'ends_at'        => $p->ends_at?->format('d/m/Y H:i'),
                'is_active'      => $p->isActive(),
                'is_expired'     => $p->isExpired(),
                'revoked_at'     => $p->revoked_at?->format('d/m/Y H:i'),
                'revoked_reason' => $p->revoked_reason,
                'user'           => ['id' => $p->user->id, 'name' => $p->user->name, 'username' => $p->user->username],
                'issued_by'      => ['name' => $p->issuedBy->name ?? '?'],
                'revoked_by'     => $p->revokedBy ? ['name' => $p->revokedBy->name] : null,
            ]);

        return Inertia::render('Admin/Punishments', [
            'punishments'  => $list,
            'query'        => $q,
            'typeFilter'   => $type,
            'statusFilter' => $status,
        ]);
    }

    public function createPunishment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type'    => 'required|in:warning,mute,suspension,ban',
            'reason'  => 'required|string|max:1000',
            'notes'   => 'nullable|string|max:2000',
            'ends_at' => 'nullable|date|after:now',
        ]);

        $target = User::findOrFail($data['user_id']);

        $this->punishments->punish($target, auth()->user(), $data);

        return back()->with('status', "Punição aplicada a {$target->name}.");
    }

    public function revokePunishment(Request $request, UserPunishment $punishment): RedirectResponse
    {
        $data = $request->validate([
            'revoked_reason' => 'nullable|string|max:500',
        ]);

        $this->punishments->revoke($punishment, auth()->user(), $data['revoked_reason'] ?? null);

        return back()->with('status', 'Punição revogada.');
    }

    // ── Audit Logs ────────────────────────────────────────────────

    public function auditLogs(Request $request): Response
    {
        $userId      = $request->get('user_id');
        $ip          = $request->get('ip');
        $action      = $request->get('action');
        $category    = $request->get('category');
        $communityId = $request->get('community_id');
        $from        = $request->get('from');
        $to          = $request->get('to');

        $logs = AuditLog::with(['actor', 'targetUser', 'community'])
            ->when($userId, fn ($q) => $q->where(fn ($q2) => $q2->where('actor_id', $userId)->orWhere('target_user_id', $userId)))
            ->when($ip, fn ($q) => $q->where('ip_address', $ip))
            ->when($action, fn ($q) => $q->where('action', 'like', "%$action%"))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at')
            ->paginate(50)
            ->through(fn ($l) => [
                'id'          => $l->id,
                'actor'       => $l->actor ? ['id' => $l->actor->id, 'name' => $l->actor->name, 'username' => $l->actor->username] : null,
                'action'      => $l->action,
                'category'    => $l->category,
                'target_type' => $l->target_type ? class_basename($l->target_type) : null,
                'target_user' => $l->targetUser ? ['id' => $l->targetUser->id, 'name' => $l->targetUser->name] : null,
                'community'   => $l->community ? ['id' => $l->community->id, 'label' => $l->community->label] : null,
                'ip_address'  => $l->ip_address,
                'method'      => $l->method,
                'route_name'  => $l->route_name,
                'metadata'    => $l->metadata,
                'created_at'  => $l->created_at->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Admin/AuditLogs', [
            'logs'        => $logs,
            'filters'     => compact('userId', 'ip', 'action', 'category', 'communityId', 'from', 'to'),
        ]);
    }
}
