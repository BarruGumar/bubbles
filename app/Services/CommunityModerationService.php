<?php

namespace App\Services;

use App\Models\Bubble;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommunityModerationService
{
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
                'community_id'   => $bubble->id,
                'community_name' => $bubble->label,
                'reason'         => $data['reason'] ?? null,
                'banned_until'   => $data['banned_until'] ?? 'permanent',
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
                'community_id'   => $bubble->id,
                'community_name' => $bubble->label,
                'reason'         => $data['reason'] ?? null,
                'muted_until'    => $data['muted_until'] ?? 'permanent',
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
                'community_id'   => $bubble->id,
                'community_name' => $bubble->label,
                'old_role'       => $old,
                'new_role'       => $role,
            ],
            $bubble->id
        );
    }

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
