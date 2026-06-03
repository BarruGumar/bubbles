<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPunishment;
use Illuminate\Support\Facades\Cache;

class PunishmentService
{
    // ── Global punishments ────────────────────────────────────────

    public function punish(User $target, User $admin, array $data): UserPunishment
    {
        abort_if($target->id === $admin->id, 403, 'Não podes punir a ti próprio.');
        abort_if($target->isSiteOwner(), 403, 'Não é possível punir o Dono do Site.');
        abort_if(! $admin->canManageUser($target), 403, 'Não tens permissão para punir este utilizador.');

        $punishment = UserPunishment::create([
            'user_id'   => $target->id,
            'issued_by' => $admin->id,
            'type'      => $data['type'],
            'reason'    => $data['reason'],
            'notes'     => $data['notes'] ?? null,
            'starts_at' => now(),
            'ends_at'   => $data['ends_at'] ?? null,
        ]);

        if ($data['type'] === 'ban') {
            $target->update(['role' => 'banned']);
        } elseif ($data['type'] === 'suspension') {
            $target->update(['role' => 'suspended']);
        }

        Cache::forget("user:{$target->id}:new_punishment");
        Cache::forget("user:{$target->id}:active_punishments");

        AuditLogger::log(
            'user.punish',
            'moderation',
            $target,
            [
                'punishment_id' => $punishment->id,
                'type'          => $data['type'],
                'reason'        => $data['reason'],
                'ends_at'       => $data['ends_at'] ?? 'permanent',
            ]
        );

        return $punishment;
    }

    public function revoke(UserPunishment $punishment, User $admin, ?string $reason = null): void
    {
        abort_if($punishment->user_id === $admin->id, 403, 'Não podes revogar a tua própria punição.');

        $punishment->update([
            'revoked_at'     => now(),
            'revoked_by'     => $admin->id,
            'revoked_reason' => $reason,
        ]);

        if (in_array($punishment->type, ['ban', 'suspension'])) {
            $punishment->user->update(['role' => 'user']);
        }

        Cache::forget("user:{$punishment->user_id}:new_punishment");
        Cache::forget("user:{$punishment->user_id}:active_punishments");

        AuditLogger::log(
            'user.punish.revoke',
            'moderation',
            $punishment->user,
            [
                'punishment_id'  => $punishment->id,
                'type'           => $punishment->type,
                'revoked_reason' => $reason,
            ]
        );
    }

}
