<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PunishmentType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPunishment;
use App\Services\PunishmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PunishmentController extends Controller
{
    public function __construct(private PunishmentService $punishments) {}

    public function index(Request $request): Response
    {
        $q      = $request->get('q');
        $type   = $request->get('type');
        $status = $request->get('status', 'active');
        $like   = $q ? ('%' . addcslashes($q, '%_\\') . '%') : null;

        $list = UserPunishment::with(['user', 'issuedBy', 'revokedBy'])
            ->when($like, fn ($query) => $query->whereHas(
                'user',
                fn ($u) => $u->where('name', 'like', $like)->orWhere('username', 'like', $like)
            ))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status === 'active',  fn ($query) => $query->active())
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type'    => ['required', Rule::enum(PunishmentType::class)],
            'reason'  => 'required|string|max:1000',
            'notes'   => 'nullable|string|max:2000',
            'ends_at' => 'nullable|date|after:now',
        ]);

        $target = User::findOrFail($data['user_id']);

        $this->punishments->punish($target, auth()->user(), $data);

        return back()->with('status', "Punição aplicada a {$target->name}.");
    }

    public function revoke(Request $request, UserPunishment $punishment): RedirectResponse
    {
        $data = $request->validate([
            'revoked_reason' => 'nullable|string|max:500',
        ]);

        $this->punishments->revoke($punishment, auth()->user(), $data['revoked_reason'] ?? null);

        return back()->with('status', 'Punição revogada.');
    }
}
