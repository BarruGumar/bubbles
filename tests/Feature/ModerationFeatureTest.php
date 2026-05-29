<?php

namespace Tests\Feature;

use App\Models\Bubble;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationFeatureTest extends TestCase
{
    use RefreshDatabase;

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

    private function attachMember(Bubble $bubble, User $user, string $role = 'member'): void
    {
        $bubble->memberships()->attach($user->id, [
            'role'      => $role,
            'status'    => 'active',
            'joined_at' => now(),
        ]);
    }

    // ── Acesso banido à comunidade ────────────────────────────────

    public function test_banned_member_cannot_view_community(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $bubble->memberships()->attach($member->id, [
            'role'      => 'member',
            'status'    => 'banned',
            'joined_at' => now(),
        ]);

        $this->actingAs($member)->get("/c/{$bubble->id}")->assertForbidden();
    }

    public function test_non_banned_member_can_view_community(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);

        $this->actingAs($member)->get("/c/{$bubble->id}")->assertOk();
    }

    // ── Ban / Unban ───────────────────────────────────────────────

    public function test_owner_can_ban_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);

        $this->actingAs($owner)->post("/c/{$bubble->id}/moderation/ban", [
            'user_id' => $member->id,
            'reason'  => 'Violação das regras da comunidade.',
        ])->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'banned',
        ]);
    }

    public function test_cannot_ban_community_owner(): void
    {
        $owner  = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        // Moderator tries to ban the owner
        $moderator = User::factory()->create();
        $this->attachMember($bubble, $moderator, 'moderator');

        $this->actingAs($moderator)->post("/c/{$bubble->id}/moderation/ban", [
            'user_id' => $owner->id,
            'reason'  => 'Tentativa indevida.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $owner->id,
            'status'       => 'banned',
        ]);
    }

    public function test_regular_member_cannot_ban(): void
    {
        $owner   = User::factory()->create();
        $member  = User::factory()->create();
        $target  = User::factory()->create();
        $bubble  = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);
        $this->attachMember($bubble, $target);

        $this->actingAs($member)->post("/c/{$bubble->id}/moderation/ban", [
            'user_id' => $target->id,
            'reason'  => 'Tentativa sem permissão.',
        ])->assertForbidden();
    }

    public function test_owner_can_unban_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $bubble->memberships()->attach($member->id, [
            'role'      => 'member',
            'status'    => 'banned',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete("/c/{$bubble->id}/moderation/ban/{$member->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'active',
        ]);
    }

    // ── Mute / Unmute ─────────────────────────────────────────────

    public function test_owner_can_mute_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);

        $this->actingAs($owner)->post("/c/{$bubble->id}/moderation/mute", [
            'user_id' => $member->id,
            'reason'  => 'Spam repetido.',
        ])->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'muted',
        ]);
    }

    public function test_owner_can_unmute_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $bubble->memberships()->attach($member->id, [
            'role'      => 'member',
            'status'    => 'muted',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete("/c/{$bubble->id}/moderation/mute/{$member->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'status'       => 'active',
        ]);
    }

    public function test_cannot_mute_community_owner(): void
    {
        $owner     = User::factory()->create();
        $moderator = User::factory()->create();
        $bubble    = $this->makeCommunity($owner);
        $this->attachMember($bubble, $moderator, 'moderator');

        $this->actingAs($moderator)->post("/c/{$bubble->id}/moderation/mute", [
            'user_id' => $owner->id,
            'reason'  => 'Tentativa indevida.',
        ])->assertForbidden();
    }

    // ── Atualizar role ────────────────────────────────────────────

    public function test_owner_can_promote_member_to_moderator(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);

        $this->actingAs($owner)
            ->patch("/c/{$bubble->id}/members/{$member->id}/role", ['role' => 'moderator'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id'      => $member->id,
            'role'         => 'moderator',
        ]);
    }

    public function test_cannot_update_role_of_community_owner(): void
    {
        $owner  = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($owner)
            ->patch("/c/{$bubble->id}/members/{$owner->id}/role", ['role' => 'member'])
            ->assertStatus(422);
    }

    public function test_cannot_update_role_of_non_member(): void
    {
        $owner    = User::factory()->create();
        $outsider = User::factory()->create();
        $bubble   = $this->makeCommunity($owner);

        // outsider is NOT attached to the community

        $this->actingAs($owner)
            ->patch("/c/{$bubble->id}/members/{$outsider->id}/role", ['role' => 'moderator'])
            ->assertStatus(422);
    }

    public function test_regular_member_cannot_update_roles(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $target = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $this->attachMember($bubble, $member);
        $this->attachMember($bubble, $target);

        $this->actingAs($member)
            ->patch("/c/{$bubble->id}/members/{$target->id}/role", ['role' => 'moderator'])
            ->assertForbidden();
    }
}
