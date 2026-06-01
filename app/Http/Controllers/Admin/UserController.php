<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->get('q');
        $actor = auth()->user();

        $users = User::when($q, fn ($query) => $query->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%"))
            ->withCount([
                'punishments as active_punishments_count' => fn ($q) => $q->active(),
                'posts as posts_count'                    => fn ($q) => $q->withTrashed(),
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($u) => [
                'id'                       => $u->id,
                'name'                     => $u->name,
                'username'                 => $u->username,
                'email'                    => $u->email,
                'role'                     => $u->role,
                'avatar'                   => $u->avatar,
                'avatar_color'             => $u->avatar_color ?? '#009ac7',
                'posts_count'              => $u->posts_count,
                'active_punishments_count' => $u->active_punishments_count,
                'is_banned'                => $u->isBanned(),
                'is_suspended'             => $u->isSuspended(),
                'is_muted'                 => $u->isGloballyMuted(),
                'created_at'               => $u->created_at->format('d/m/Y'),
                'can_manage'               => $actor->canManageUser($u),
            ]);

        return Inertia::render('Admin/Users', [
            'users'       => $users,
            'query'       => $q,
            'isSiteOwner' => $actor->isSiteOwner(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = auth()->user();

        $allowedRoles = $actor->isSiteOwner()
            ? UserRole::values()
            : UserRole::assignableByAdmin();

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        if ($user->id === $actor->id) {
            return back()->with('error', 'Não podes alterar o teu próprio papel.');
        }

        if (($user->isSiteOwner() || $data['role'] === UserRole::SiteOwner->value) && ! $actor->isSiteOwner()) {
            abort(403, 'Apenas o Dono do Site pode gerir este cargo.');
        }

        if ($user->isSiteOwner() && $data['role'] !== UserRole::SiteOwner->value) {
            $ownerCount = User::where('role', UserRole::SiteOwner->value)->count();
            if ($ownerCount <= 1) {
                return back()->with('error', 'Não é possível remover o único Dono do Site.');
            }
        }

        $old = $user->role;
        $user->update(['role' => $data['role']]);

        AuditLogger::log('user.role.update', 'admin', $user, ['old_role' => $old, 'new_role' => $data['role']]);

        return back()->with('status', 'Papel do utilizador atualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $actor = auth()->user();

        if ($user->id === $actor->id) {
            return back()->with('error', 'Não podes apagar a tua própria conta.');
        }

        if ($user->isSiteOwner()) {
            return back()->with('error', 'Não é possível apagar o Dono do Site.');
        }

        if ($user->isAdmin() && ! $actor->isSiteOwner()) {
            return back()->with('error', 'Apenas o Dono do Site pode apagar um admin.');
        }

        AuditLogger::log('user.delete', 'admin', $user, ['username' => $user->username]);
        $user->delete();

        return back()->with('status', 'Utilizador eliminado.');
    }
}
