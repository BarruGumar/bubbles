<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class ConversationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeDirect(User $a, User $b): Conversation
    {
        $conv = Conversation::create(['type' => 'direct']);
        $conv->participants()->attach([
            $a->id => ['role' => 'member', 'joined_at' => now()],
            $b->id => ['role' => 'member', 'joined_at' => now()],
        ]);
        return $conv;
    }

    // ── Index ──────────────────────────────────────────────────────

    public function test_guest_cannot_view_conversations(): void
    {
        $this->get('/conversations')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_conversations_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/conversations')->assertOk();
    }

    // ── Store ──────────────────────────────────────────────────────

    public function test_user_can_start_direct_conversation(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1)
            ->post('/conversations', ['recipient_id' => $u2->id])
            ->assertRedirect();

        $this->assertDatabaseHas('conversations', ['type' => 'direct']);
    }

    public function test_cannot_start_conversation_with_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/conversations', ['recipient_id' => $user->id])
            ->assertStatus(422);
    }

    public function test_starting_conversation_twice_reuses_existing(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1)->post('/conversations', ['recipient_id' => $u2->id]);
        $this->actingAs($u1)->post('/conversations', ['recipient_id' => $u2->id]);

        $this->assertDatabaseCount('conversations', 1);
    }

    // ── Show ───────────────────────────────────────────────────────

    public function test_participant_can_view_conversation(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $this->actingAs($u1)->get("/conversations/{$conv->id}")->assertOk();
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $outsider = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $this->actingAs($outsider)->get("/conversations/{$conv->id}")->assertForbidden();
    }

    // ── storeMessage ───────────────────────────────────────────────

    public function test_participant_can_send_message(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $this->actingAs($u1)
            ->postJson("/conversations/{$conv->id}/messages", ['content' => 'Hello!'])
            ->assertOk()
            ->assertJsonFragment(['content' => 'Hello!']);

        $this->assertDatabaseHas('messages', ['conversation_id' => $conv->id, 'content' => 'Hello!']);
    }

    public function test_banned_user_cannot_send_message(): void
    {
        $banned = User::factory()->create(['role' => 'banned']);
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($banned, $u2);

        $this->actingAs($banned)
            ->postJson("/conversations/{$conv->id}/messages", ['content' => 'Hello!'])
            ->assertRedirect('/');

        $this->assertDatabaseEmpty('messages');
    }

    public function test_suspended_user_cannot_send_message(): void
    {
        $suspended = User::factory()->create(['role' => 'suspended']);
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($suspended, $u2);

        $this->actingAs($suspended)
            ->postJson("/conversations/{$conv->id}/messages", ['content' => 'Hello!'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('messages');
    }

    public function test_message_content_is_required(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $this->actingAs($u1)
            ->postJson("/conversations/{$conv->id}/messages", ['content' => ''])
            ->assertUnprocessable();
    }

    // ── updateMessage ──────────────────────────────────────────────

    public function test_owner_can_update_message(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'user_id'         => $u1->id,
            'content'         => 'Original',
        ]);

        $this->actingAs($u1)
            ->patchJson("/messages/{$msg->id}", ['content' => 'Edited'])
            ->assertOk()
            ->assertJsonFragment(['content' => 'Edited']);

        $this->assertDatabaseHas('messages', ['id' => $msg->id, 'content' => 'Edited']);
    }

    public function test_non_owner_cannot_update_message(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'user_id'         => $u1->id,
            'content'         => 'Original',
        ]);

        $this->actingAs($u2)
            ->patchJson("/messages/{$msg->id}", ['content' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $msg->id, 'content' => 'Original']);
    }

    // ── destroyMessage ─────────────────────────────────────────────

    public function test_owner_can_delete_message(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'user_id'         => $u1->id,
            'content'         => 'To be deleted',
        ]);

        $this->actingAs($u1)
            ->deleteJson("/messages/{$msg->id}")
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $msg->id]);
    }

    public function test_non_owner_cannot_delete_message(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conv = $this->makeDirect($u1, $u2);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'user_id'         => $u1->id,
            'content'         => 'Protected',
        ]);

        $this->actingAs($u2)
            ->deleteJson("/messages/{$msg->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $msg->id]);
    }
}
