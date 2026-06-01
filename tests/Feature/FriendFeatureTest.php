<?php

namespace Tests\Feature;

use App\Models\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class FriendFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ── Enviar pedido ─────────────────────────────────────────────

    public function test_user_can_send_friend_request(): void
    {
        $sender = User::factory()->create();
        $target = User::factory()->create(['username' => 'alice']);

        $this->actingAs($sender)->post('/friends/alice')->assertRedirect();

        $this->assertDatabaseHas('friends', [
            'user_id'   => $sender->id,
            'friend_id' => $target->id,
            'status'    => 'pending',
        ]);
    }

    public function test_banned_user_cannot_send_friend_request(): void
    {
        $sender = User::factory()->create(['role' => 'banned']);
        User::factory()->create(['username' => 'alice']);

        // Banned users are logged out and redirected to '/' by CheckActivePunishments middleware
        $this->actingAs($sender)->post('/friends/alice')->assertRedirect('/');

        $this->assertDatabaseEmpty('friends');
    }

    public function test_suspended_user_cannot_send_friend_request(): void
    {
        $sender = User::factory()->create(['role' => 'suspended']);
        User::factory()->create(['username' => 'alice']);

        $this->actingAs($sender)->post('/friends/alice')->assertForbidden();

        $this->assertDatabaseEmpty('friends');
    }

    public function test_cannot_send_friend_request_to_self(): void
    {
        $user = User::factory()->create(['username' => 'myself']);

        $this->actingAs($user)->post('/friends/myself')->assertStatus(422);

        $this->assertDatabaseEmpty('friends');
    }

    public function test_duplicate_request_does_not_create_second_record(): void
    {
        $sender = User::factory()->create();
        User::factory()->create(['username' => 'bob']);

        $this->actingAs($sender)->post('/friends/bob');
        $this->actingAs($sender)->post('/friends/bob');

        $this->assertDatabaseCount('friends', 1);
    }

    public function test_guest_cannot_send_friend_request(): void
    {
        User::factory()->create(['username' => 'alice']);

        $this->post('/friends/alice')->assertRedirect('/login');

        $this->assertDatabaseEmpty('friends');
    }

    // ── Aceitar pedido ────────────────────────────────────────────

    public function test_recipient_can_accept_friend_request(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'pending',
        ]);

        $this->actingAs($recipient)
            ->patch("/friends/{$friend->id}/accept")
            ->assertRedirect();

        $this->assertDatabaseHas('friends', ['id' => $friend->id, 'status' => 'accepted']);
    }

    public function test_sender_cannot_accept_own_request(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'pending',
        ]);

        $this->actingAs($sender)
            ->patch("/friends/{$friend->id}/accept")
            ->assertForbidden();

        $this->assertDatabaseHas('friends', ['id' => $friend->id, 'status' => 'pending']);
    }

    public function test_cannot_accept_already_accepted_request(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'accepted',
        ]);

        $this->actingAs($recipient)
            ->patch("/friends/{$friend->id}/accept")
            ->assertStatus(422);
    }

    // ── Recusar / cancelar ────────────────────────────────────────

    public function test_recipient_can_reject_received_request(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'pending',
        ]);

        $this->actingAs($recipient)
            ->delete("/friends/{$friend->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('friends', ['id' => $friend->id]);
    }

    public function test_sender_can_cancel_sent_request(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'pending',
        ]);

        $this->actingAs($sender)
            ->delete("/friends/{$friend->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('friends', ['id' => $friend->id]);
    }

    public function test_unrelated_user_cannot_delete_friend_record(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();
        $intruder  = User::factory()->create();

        $friend = Friend::create([
            'user_id'   => $sender->id,
            'friend_id' => $recipient->id,
            'status'    => 'pending',
        ]);

        $this->actingAs($intruder)
            ->delete("/friends/{$friend->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('friends', ['id' => $friend->id]);
    }
}
