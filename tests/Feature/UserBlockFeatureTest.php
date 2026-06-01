<?php

namespace Tests\Feature;

use App\Models\Friend;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBlockFeatureTest extends TestCase
{
    use RefreshDatabase;

    // ── Block ──────────────────────────────────────────────────────

    public function test_guest_cannot_block(): void
    {
        User::factory()->create(['username' => 'alice']);
        $this->post('/users/alice/block')->assertRedirect('/login');
        $this->assertDatabaseEmpty('user_blocks');
    }

    public function test_user_can_block_another_user(): void
    {
        $blocker = User::factory()->create();
        User::factory()->create(['username' => 'alice']);

        $this->actingAs($blocker)->post('/users/alice/block')->assertRedirect();

        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $blocker->id]);
    }

    public function test_cannot_block_self(): void
    {
        $user = User::factory()->create(['username' => 'myself']);

        $this->actingAs($user)->post('/users/myself/block')->assertStatus(422);
        $this->assertDatabaseEmpty('user_blocks');
    }

    public function test_blocking_removes_existing_friendship(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create(['username' => 'bob']);

        Friend::create(['user_id' => $u1->id, 'friend_id' => $u2->id, 'status' => 'accepted']);

        $this->actingAs($u1)->post('/users/bob/block');

        $this->assertDatabaseEmpty('friends');
    }

    public function test_blocking_twice_does_not_create_duplicate(): void
    {
        $blocker = User::factory()->create();
        User::factory()->create(['username' => 'alice']);

        $this->actingAs($blocker)->post('/users/alice/block');
        $this->actingAs($blocker)->post('/users/alice/block');

        $this->assertDatabaseCount('user_blocks', 1);
    }

    // ── Unblock ────────────────────────────────────────────────────

    public function test_user_can_unblock_another_user(): void
    {
        $blocker = User::factory()->create();
        $target  = User::factory()->create(['username' => 'alice']);

        UserBlock::create(['blocker_id' => $blocker->id, 'blocked_id' => $target->id]);

        $this->actingAs($blocker)->delete('/users/alice/block')->assertRedirect();

        $this->assertDatabaseEmpty('user_blocks');
    }

    // ── Friend request blocked ─────────────────────────────────────

    public function test_blocked_user_cannot_send_friend_request(): void
    {
        $u1 = User::factory()->create(['username' => 'alice']);
        $u2 = User::factory()->create();

        UserBlock::create(['blocker_id' => $u1->id, 'blocked_id' => $u2->id]);

        $this->actingAs($u2)->post('/friends/alice')->assertStatus(422);
        $this->assertDatabaseEmpty('friends');
    }

    public function test_user_who_blocked_cannot_send_friend_request(): void
    {
        $u1 = User::factory()->create(['username' => 'alice']);
        $u2 = User::factory()->create();

        UserBlock::create(['blocker_id' => $u2->id, 'blocked_id' => $u1->id]);

        $this->actingAs($u2)->post('/friends/alice')->assertStatus(422);
        $this->assertDatabaseEmpty('friends');
    }

    // ── Search filtered ────────────────────────────────────────────

    public function test_blocked_user_does_not_appear_in_search(): void
    {
        $me      = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'Bob BlockedUser', 'username' => 'bob_blocked']);

        UserBlock::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $response = $this->actingAs($me)->getJson('/api/search?q=bob_blocked')->assertOk();

        $this->assertEmpty($response->json('users'));
    }

    public function test_user_who_blocked_me_does_not_appear_in_search(): void
    {
        $me      = User::factory()->create();
        $blocker = User::factory()->create(['name' => 'Charlie Blocker', 'username' => 'charlie_blocks']);

        UserBlock::create(['blocker_id' => $blocker->id, 'blocked_id' => $me->id]);

        $response = $this->actingAs($me)->getJson('/api/search?q=charlie_blocks')->assertOk();

        $this->assertEmpty($response->json('users'));
    }

    // ── Conversation blocked ───────────────────────────────────────

    public function test_cannot_start_conversation_with_blocked_user(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        UserBlock::create(['blocker_id' => $u1->id, 'blocked_id' => $u2->id]);

        $this->actingAs($u1)
            ->post('/conversations', ['recipient_id' => $u2->id])
            ->assertStatus(422);

        $this->assertDatabaseEmpty('conversations');
    }

    public function test_cannot_start_conversation_with_user_who_blocked_me(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        UserBlock::create(['blocker_id' => $u2->id, 'blocked_id' => $u1->id]);

        $this->actingAs($u1)
            ->post('/conversations', ['recipient_id' => $u2->id])
            ->assertStatus(422);

        $this->assertDatabaseEmpty('conversations');
    }
}
