<?php

namespace App\Services;

use App\Models\Bubble;
use App\Models\User;
use App\Models\UserPunishment;
use Illuminate\Support\Facades\DB;

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

    // ── Community bans ────────────────────────────────────────────

    public function banFromCommunity(User $target, Bubble $bubble, User $by, array $data): void
    {
        abort_if($target->id === $bubble->user_id, 403, 'Não podes banir o criador da comunidade.');
        abort_if($target->isSiteOwner(), 403, 'Não é possível banir o Dono do Site de uma comunidade.');
        abort_if($target->id === $by->id, 403, 'Não podes banir a ti próprio.');

        $this->ensureMembership($target, $bubble);

        DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->update([
                'status'       => 'banned',
                'banned_at'    => now(),
                'banned_until' => $data['banned_until'] ?? null,
                'banned_by'    => $by->id,
                'ban_reason'   => $data['reason'] ?? null,
                'muted_until'  => null,
                'muted_by'     => null,
                'mute_reason'  => null,
            ]);

        AuditLogger::log(
            'community.ban',
            'community',
            $target,
            [
                'community_id'  => $bubble->id,
                'community_name' => $bubble->label,
                'reason'        => $data['reason'] ?? null,
                'banned_until'  => $data['banned_until'] ?? 'permanent',
            ],
            $bubble->id
        );
    }

    public function unbanFromCommunity(User $target, Bubble $bubble, User $by): void
    {
        DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->update([
                'status'       => 'active',
                'banned_at'    => null,
                'banned_until' => null,
                'banned_by'    => null,
                'ban_reason'   => null,
            ]);

        AuditLogger::log(
            'community.unban',
            'community',
            $target,
            ['community_id' => $bubble->id, 'community_name' => $bubble->label],
            $bubble->id
        );
    }

    // ── Community mutes ───────────────────────────────────────────

    public function muteInCommunity(User $target, Bubble $bubble, User $by, array $data): void
    {
        abort_if($target->id === $bubble->user_id, 403, 'Não podes mutar o criador da comunidade.');
        abort_if($target->isSiteOwner(), 403, 'Não é possível mutar o Dono do Site.');
        abort_if($target->id === $by->id, 403, 'Não podes mutar a ti próprio.');

        $this->ensureMembership($target, $bubble);

        DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->update([
                'status'      => 'muted',
                'muted_until' => $data['muted_until'] ?? null,
                'muted_by'    => $by->id,
                'mute_reason' => $data['reason'] ?? null,
            ]);

        AuditLogger::log(
            'community.mute',
            'community',
            $target,
            [
                'community_id'  => $bubble->id,
                'community_name' => $bubble->label,
                'reason'        => $data['reason'] ?? null,
                'muted_until'   => $data['muted_until'] ?? 'permanent',
            ],
            $bubble->id
        );
    }

    public function unmuteInCommunity(User $target, Bubble $bubble, User $by): void
    {
        DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->update([
                'status'      => 'active',
                'muted_until' => null,
                'muted_by'    => null,
                'mute_reason' => null,
            ]);

        AuditLogger::log(
            'community.unmute',
            'community',
            $target,
            ['community_id' => $bubble->id, 'community_name' => $bubble->label],
            $bubble->id
        );
    }

    // ── Community role ────────────────────────────────────────────

    public function updateCommunityRole(User $target, Bubble $bubble, User $by, string $role): void
    {
        abort_if($target->id === $bubble->user_id, 403, 'Não podes alterar o papel do criador.');
        abort_if(! in_array($role, ['member', 'moderator', 'admin']), 422, 'Papel inválido.');

        $this->ensureMembership($target, $bubble);

        $old = DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->value('role');

        DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->update(['role' => $role]);

        AuditLogger::log(
            'community.role.update',
            'community',
            $target,
            [
                'community_id'  => $bubble->id,
                'community_name' => $bubble->label,
                'old_role'      => $old,
                'new_role'      => $role,
            ],
            $bubble->id
        );
    }

    // ── Private helpers ───────────────────────────────────────────

    private function ensureMembership(User $target, Bubble $bubble): void
    {
        $exists = DB::table('community_user')
            ->where('user_id', $target->id)
            ->where('community_id', $bubble->id)
            ->exists();

        if (! $exists) {
            DB::table('community_user')->insert([
                'user_id'      => $target->id,
                'community_id' => $bubble->id,
                'role'         => 'member',
                'status'       => 'active',
                'joined_at'    => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}
