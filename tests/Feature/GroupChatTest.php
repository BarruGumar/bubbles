<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function makeUsers(int $count): array
    {
        return User::factory($count)->create()->all();
    }

    private function makeGroup(User $owner, array $members, string $name = 'Test Group'): Conversation
    {
        $conv = Conversation::create([
            'type'     => 'group',
            'name'     => $name,
            'owner_id' => $owner->id,
        ]);

        $conv->participants()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        foreach ($members as $member) {
            $conv->participants()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }

        return $conv;
    }

    // ── Creation ──────────────────────────────────────────────────

    public function test_can_create_group_with_valid_data(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);

        $this->actingAs($owner)
            ->post('/groups', [
                'name'              => 'My Group',
                'participant_ids'   => [$m1->id, $m2->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', ['type' => 'group', 'name' => 'My Group']);

        $conv = Conversation::where('name', 'My Group')->first();
        $this->assertCount(3, $conv->participants);
        $this->assertEquals('owner', $conv->participants()->where('user_id', $owner->id)->value('conversation_user.role'));
        $this->assertEquals('member', $conv->participants()->where('user_id', $m1->id)->value('conversation_user.role'));
    }

    public function test_cannot_create_group_with_only_one_participant(): void
    {
        [$owner, $m1] = $this->makeUsers(2);

        $this->actingAs($owner)
            ->post('/groups', [
                'name'            => 'Too Small',
                'participant_ids' => [$m1->id],
            ])
            ->assertSessionHasErrors('participant_ids');

        $this->assertDatabaseMissing('conversations', ['name' => 'Too Small']);
    }

    public function test_cannot_create_group_without_name(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);

        $this->actingAs($owner)
            ->post('/groups', [
                'participant_ids' => [$m1->id, $m2->id],
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_guest_cannot_create_group(): void
    {
        [$m1, $m2] = $this->makeUsers(2);

        $this->post('/groups', [
            'name'            => 'Group',
            'participant_ids' => [$m1->id, $m2->id],
        ])->assertRedirect('/login');
    }

    // ── Member management ─────────────────────────────────────────

    public function test_owner_can_add_member(): void
    {
        [$owner, $m1, $m2, $newMember] = $this->makeUsers(4);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->postJson("/groups/{$group->id}/members", ['user_id' => $newMember->id])
            ->assertOk();

        $this->assertDatabaseHas('conversation_user', [
            'conversation_id' => $group->id,
            'user_id'         => $newMember->id,
            'role'            => 'member',
        ]);
    }

    public function test_admin_can_add_member(): void
    {
        [$owner, $admin, $m1, $newMember] = $this->makeUsers(4);
        $group = $this->makeGroup($owner, [$m1]);
        $group->participants()->updateExistingPivot($admin->id, ['role' => 'admin']);

        // Attach admin properly first
        $group->participants()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);

        $this->actingAs($admin)
            ->postJson("/groups/{$group->id}/members", ['user_id' => $newMember->id])
            ->assertOk();
    }

    public function test_member_cannot_add_another_member(): void
    {
        [$owner, $m1, $m2, $newMember] = $this->makeUsers(4);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->postJson("/groups/{$group->id}/members", ['user_id' => $newMember->id])
            ->assertForbidden();
    }

    public function test_owner_can_remove_member(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->deleteJson("/groups/{$group->id}/members/{$m1->id}")
            ->assertOk();

        $this->assertDatabaseMissing('conversation_user', [
            'conversation_id' => $group->id,
            'user_id'         => $m1->id,
        ]);
    }

    public function test_owner_cannot_be_removed(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->deleteJson("/groups/{$group->id}/members/{$owner->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_remove_anyone(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->deleteJson("/groups/{$group->id}/members/{$m2->id}")
            ->assertForbidden();
    }

    // ── Promote / demote ──────────────────────────────────────────

    public function test_owner_can_promote_member_to_admin(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->patchJson("/groups/{$group->id}/promote", ['user_id' => $m1->id])
            ->assertOk()
            ->assertJson(['role' => 'admin']);

        $this->assertEquals('admin', $group->participants()->where('user_id', $m1->id)->value('conversation_user.role'));
    }

    public function test_member_cannot_promote(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->patchJson("/groups/{$group->id}/promote", ['user_id' => $m2->id])
            ->assertForbidden();
    }

    public function test_owner_can_demote_admin_to_member(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);
        $group->participants()->updateExistingPivot($m1->id, ['role' => 'admin']);

        $this->actingAs($owner)
            ->patchJson("/groups/{$group->id}/demote", ['user_id' => $m1->id])
            ->assertOk()
            ->assertJson(['role' => 'member']);
    }

    // ── Leave group ───────────────────────────────────────────────

    public function test_member_can_leave_group(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->deleteJson("/groups/{$group->id}/leave")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('conversation_user', [
            'conversation_id' => $group->id,
            'user_id'         => $m1->id,
        ]);
    }

    public function test_owner_cannot_leave_without_transferring(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->deleteJson("/groups/{$group->id}/leave")
            ->assertUnprocessable();
    }

    public function test_owner_can_transfer_ownership(): void
    {
        [$owner, $m1, $m2] = $this->makeUsers(3);
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->patchJson("/groups/{$group->id}/owner", ['user_id' => $m1->id])
            ->assertOk();

        $this->assertEquals('owner', $group->participants()->where('user_id', $m1->id)->value('conversation_user.role'));
        $this->assertEquals('admin', $group->participants()->where('user_id', $owner->id)->value('conversation_user.role'));
        $this->assertEquals($m1->id, $group->fresh()->owner_id);
    }

    // ── Compatibility with direct conversations ───────────────────

    public function test_direct_conversation_is_unaffected_by_group_routes(): void
    {
        [$u1, $u2] = $this->makeUsers(2);

        $direct = Conversation::create(['type' => 'direct']);
        $direct->participants()->attach($u1->id, ['role' => 'member', 'joined_at' => now()]);
        $direct->participants()->attach($u2->id, ['role' => 'member', 'joined_at' => now()]);

        // Group endpoints reject direct conversations
        $this->actingAs($u1)
            ->postJson("/groups/{$direct->id}/members", ['user_id' => $u2->id])
            ->assertForbidden();
    }

    public function test_existing_direct_conversations_have_direct_type(): void
    {
        [$u1, $u2] = $this->makeUsers(2);

        // Simulate old conversation with no type set (migrated data has default 'direct')
        $conv = Conversation::create(['type' => 'direct']);
        $this->assertEquals('direct', $conv->type);
        $this->assertTrue($conv->isDirect());
        $this->assertFalse($conv->isGroup());
    }
}
