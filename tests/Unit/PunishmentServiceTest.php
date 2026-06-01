<?php

namespace Tests\Unit;

use App\Models\Bubble;
use App\Models\User;
use App\Models\UserPunishment;
use App\Services\CommunityModerationService;
use App\Services\PunishmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PunishmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PunishmentService $service;
    private CommunityModerationService $communityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PunishmentService();
        $this->communityService = new CommunityModerationService();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function makeCommunity(User $owner): Bubble
    {
        $bubble = Bubble::factory()->for($owner)->create();
        $bubble->memberships()->attach($owner->id, [
            'role'      => 'member',
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        return $bubble;
    }

    private function attachMember(Bubble $bubble, User $user): void
    {
        $bubble->memberships()->attach($user->id, [
            'role'      => 'member',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
    }

    // ── Punições globais ──────────────────────────────────────────

    public function test_ban_creates_punishment_and_sets_role_to_banned(): void
    {
        $admin  = $this->admin();
        $target = $this->regularUser();
        Auth::login($admin);

        $this->service->punish($target, $admin, [
            'type'    => 'ban',
            'reason'  => 'Violação grave dos termos.',
            'ends_at' => null,
        ]);

        $this->assertDatabaseHas('user_punishments', [
            'user_id'   => $target->id,
            'issued_by' => $admin->id,
            'type'      => 'ban',
        ]);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'banned']);
    }

    public function test_suspension_creates_punishment_and_sets_role_to_suspended(): void
    {
        $admin  = $this->admin();
        $target = $this->regularUser();
        Auth::login($admin);

        $this->service->punish($target, $admin, [
            'type'    => 'suspension',
            'reason'  => 'Comportamento inapropriado.',
            'ends_at' => now()->addDays(7),
        ]);

        $this->assertDatabaseHas('user_punishments', [
            'user_id' => $target->id,
            'type'    => 'suspension',
        ]);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'suspended']);
    }

    public function test_admin_cannot_punish_themselves(): void
    {
        $admin = $this->admin();
        Auth::login($admin);

        $this->expectException(HttpException::class);

        $this->service->punish($admin, $admin, [
            'type'   => 'ban',
            'reason' => 'Tentativa inválida.',
        ]);
    }

    public function test_cannot_punish_site_owner(): void
    {
        $admin     = $this->admin();
        $siteOwner = User::factory()->create(['role' => 'site_owner']);
        Auth::login($admin);

        $this->expectException(HttpException::class);

        $this->service->punish($siteOwner, $admin, [
            'type'   => 'ban',
            'reason' => 'Tentativa inválida.',
        ]);
    }

    public function test_lower_role_cannot_punish_higher_role(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $admin     = $this->admin();
        Auth::login($moderator);

        $this->expectException(HttpException::class);

        $this->service->punish($admin, $moderator, [
            'type'   => 'suspension',
            'reason' => 'Tentativa sem hierarquia.',
        ]);
    }

    // ── Revogar punição ───────────────────────────────────────────

    public function test_revoke_fills_revoked_fields_and_restores_role(): void
    {
        $admin  = $this->admin();
        $target = $this->regularUser();
        Auth::login($admin);

        $punishment = $this->service->punish($target, $admin, [
            'type'    => 'ban',
            'reason'  => 'Ban temporário.',
            'ends_at' => null,
        ]);

        $this->service->revoke($punishment, $admin, 'Apelação aceite.');

        $this->assertDatabaseHas('user_punishments', [
            'id'             => $punishment->id,
            'revoked_by'     => $admin->id,
            'revoked_reason' => 'Apelação aceite.',
        ]);

        $this->assertNotNull($punishment->fresh()->revoked_at);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'user']);
    }

    public function test_cannot_revoke_own_punishment(): void
    {
        $admin  = $this->admin();
        $target = $this->regularUser();
        Auth::login($admin);

        $punishment = UserPunishment::create([
            'user_id'   => $admin->id,
            'issued_by' => $target->id,
            'type'      => 'suspension',
            'reason'    => 'Teste.',
            'starts_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        $this->service->revoke($punishment, $admin);
    }

    // ── Ban de comunidade ─────────────────────────────────────────

    public function test_ban_from_community_sets_pivot_status_to_banned(): void
    {
        $owner  = $this->regularUser();
        $member = $this->regularUser();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);
        Auth::login($owner);

        $this->communityService->banFromCommunity($member, $bubble, $owner, [
            'reason'       => 'Spam repetido.',
            'banned_until' => null,
        ]);

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'banned',
        ]);
    }

    public function test_cannot_ban_community_owner_from_own_community(): void
    {
        $owner     = $this->regularUser();
        $moderator = $this->regularUser();
        $bubble    = $this->makeCommunity($owner);
        $this->attachMember($bubble, $moderator);
        Auth::login($moderator);

        $this->expectException(HttpException::class);

        $this->communityService->banFromCommunity($owner, $bubble, $moderator, [
            'reason' => 'Tentativa indevida.',
        ]);
    }

    public function test_unban_from_community_restores_status_to_active(): void
    {
        $owner  = $this->regularUser();
        $member = $this->regularUser();
        $bubble = $this->makeCommunity($owner);

        $bubble->memberships()->attach($member->id, [
            'role'      => 'member',
            'status'    => 'banned',
            'joined_at' => now(),
        ]);

        Auth::login($owner);

        $this->communityService->unbanFromCommunity($member, $bubble, $owner);

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'active',
        ]);
    }

    // ── Mute de comunidade ────────────────────────────────────────

    public function test_mute_in_community_sets_pivot_status_to_muted(): void
    {
        $owner  = $this->regularUser();
        $member = $this->regularUser();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);
        Auth::login($owner);

        $this->communityService->muteInCommunity($member, $bubble, $owner, [
            'reason'      => 'Tom agressivo.',
            'muted_until' => null,
        ]);

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'muted',
        ]);
    }

    public function test_unmute_in_community_restores_status_to_active(): void
    {
        $owner  = $this->regularUser();
        $member = $this->regularUser();
        $bubble = $this->makeCommunity($owner);

        $bubble->memberships()->attach($member->id, [
            'role'      => 'member',
            'status'    => 'muted',
            'joined_at' => now(),
        ]);

        Auth::login($owner);

        $this->communityService->unmuteInCommunity($member, $bubble, $owner);

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'active',
        ]);
    }
}
