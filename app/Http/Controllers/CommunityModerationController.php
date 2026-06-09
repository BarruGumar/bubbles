<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\User;
use App\Services\CommunityModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityModerationController extends Controller
{
    public function __construct(private CommunityModerationService $punishments) {}

    // ── Member list ───────────────────────────────────────────────

    public function members(int $id, Request $request): Response
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $q      = $request->get('q');
        $filter = $request->get('filter', 'all'); // all | staff | banned | muted
        $like   = $q ? ('%' . addcslashes($q, '%_\\') . '%') : null;

        $query = User::query()
            ->join('community_user as cu', 'cu.user_id', '=', 'users.id')
            ->where('cu.community_id', $bubble->id)
            ->when($like, fn ($q2) => $q2->where(function ($q3) use ($like) {
                $q3->where('users.name', 'like', $like)
                   ->orWhere('users.username', 'like', $like);
            }))
            ->when($filter === 'staff', fn ($q2) => $q2->whereIn('cu.role', ['admin', 'moderator']))
            ->when($filter === 'banned', fn ($q2) => $q2->where('cu.status', 'banned'))
            ->when($filter === 'muted', fn ($q2) => $q2->where('cu.status', 'muted'))
            ->select(
                'users.id', 'users.name', 'users.username', 'users.avatar', 'users.avatar_color',
                'cu.role as cu_role', 'cu.status as cu_status',
                'cu.joined_at as cu_joined_at', 'cu.created_at as cu_created_at',
                'cu.banned_until as cu_banned_until', 'cu.ban_reason as cu_ban_reason',
                'cu.muted_until as cu_muted_until', 'cu.mute_reason as cu_mute_reason'
            );

        $members = $query->paginate(30)->through(fn ($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'username'     => $u->username,
            'avatar'       => $u->avatar,
            'avatar_color' => $u->avatar_color ?? '#009ac7',
            'is_owner'     => $u->id === $bubble->user_id,
            'role'         => $u->id === $bubble->user_id ? 'owner' : ($u->cu_role ?? 'member'),
            'status'       => $u->cu_status ?? 'active',
            'banned_until' => $u->cu_banned_until,
            'ban_reason'   => $u->cu_ban_reason,
            'muted_until'  => $u->cu_muted_until,
            'mute_reason'  => $u->cu_mute_reason,
            'joined_at'    => $u->cu_joined_at ?? $u->cu_created_at,
        ]);

        return Inertia::render('Community/Members', [
            'community' => [
                'id'    => $bubble->id,
                'label' => $bubble->label,
                'title' => $bubble->community_title ?: $bubble->label,
                'color' => $bubble->color ?? '#009ac7',
            ],
            'members'       => $members,
            'query'         => $q,
            'filter'        => $filter,
            'myRole'        => auth()->user()->communityRole($bubble),
        ]);
    }

    // ── Role management ───────────────────────────────────────────

    public function updateRole(int $id, Request $request, User $user): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('promote', $bubble);

        $data = $request->validate([
            'role' => 'required|in:member,moderator,admin',
        ]);

        $isOwner = $user->id === $bubble->user_id;
        $isMember = $bubble->memberships()->where('user_id', $user->id)->exists();
        abort_if(! $isOwner && ! $isMember, 422, 'Este utilizador não é membro desta comunidade.');

        // Não se pode alterar o role do criador
        abort_if($isOwner, 422, 'Não é possível alterar o papel do criador da comunidade.');

        $this->punishments->updateCommunityRole($user, $bubble, auth()->user(), $data['role']);

        return back()->with('status', 'Papel do membro atualizado.');
    }

    // ── Ban ───────────────────────────────────────────────────────

    public function ban(int $id, Request $request): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'reason'       => 'required|string|max:500',
            'banned_until' => 'nullable|date|after:now',
        ]);

        $target = User::findOrFail($data['user_id']);

        $this->punishments->banFromCommunity($target, $bubble, auth()->user(), [
            'reason'       => $data['reason'],
            'banned_until' => $data['banned_until'] ?? null,
        ]);

        return back()->with('status', "Utilizador {$target->name} banido da comunidade.");
    }

    public function unban(int $id, User $user): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $this->punishments->unbanFromCommunity($user, $bubble, auth()->user());

        return back()->with('status', "Utilizador {$user->name} desbanido da comunidade.");
    }

    // ── Mute ─────────────────────────────────────────────────────

    public function mute(int $id, Request $request): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'reason'       => 'required|string|max:500',
            'muted_until'  => 'nullable|date|after:now',
        ]);

        $target = User::findOrFail($data['user_id']);

        $this->punishments->muteInCommunity($target, $bubble, auth()->user(), [
            'reason'      => $data['reason'],
            'muted_until' => $data['muted_until'] ?? null,
        ]);

        return back()->with('status', "Utilizador {$target->name} silenciado na comunidade.");
    }

    public function unmute(int $id, User $user): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $this->punishments->unmuteInCommunity($user, $bubble, auth()->user());

        return back()->with('status', "Utilizador {$user->name} dessilenciado na comunidade.");
    }
}
