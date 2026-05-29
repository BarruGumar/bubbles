<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\User;
use App\Services\PunishmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityModerationController extends Controller
{
    public function __construct(private PunishmentService $punishments) {}

    // ── Member list ───────────────────────────────────────────────

    public function members(int $id, Request $request): Response
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('moderate', $bubble);

        $q      = $request->get('q');
        $filter = $request->get('filter', 'all'); // all | staff | banned | muted

        $query = $bubble->memberships()
            ->when($q, fn ($query) => $query->where(function ($q2) use ($q) {
                $q2->where('users.name', 'like', "%{$q}%")
                   ->orWhere('users.username', 'like', "%{$q}%");
            }))
            ->when($filter === 'staff', fn ($query) => $query->wherePivotIn('role', ['admin', 'moderator']))
            ->when($filter === 'banned', fn ($query) => $query->wherePivot('status', 'banned'))
            ->when($filter === 'muted', fn ($query) => $query->wherePivot('status', 'muted'));

        $members = $query->paginate(30)->through(fn ($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'username'     => $u->username,
            'avatar'       => $u->avatar,
            'avatar_color' => $u->avatar_color ?? '#009ac7',
            'is_owner'     => $u->id === $bubble->user_id,
            'role'         => $u->id === $bubble->user_id ? 'owner' : ($u->pivot->role ?? 'member'),
            'status'       => $u->pivot->status ?? 'active',
            'banned_until' => $u->pivot->banned_until,
            'ban_reason'   => $u->pivot->ban_reason,
            'muted_until'  => $u->pivot->muted_until,
            'mute_reason'  => $u->pivot->mute_reason,
            'joined_at'    => $u->pivot->joined_at ?? $u->pivot->created_at,
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
