<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MessageReceived;
use Tests\TestCase;

class GroupMessageTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(User $owner, array $members): Conversation
    {
        $conv = Conversation::create([
            'type'     => 'group',
            'name'     => 'Test Group',
            'owner_id' => $owner->id,
        ]);

        $conv->participants()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        foreach ($members as $m) {
            $conv->participants()->attach($m->id, ['role' => 'member', 'joined_at' => now()]);
        }

        return $conv;
    }

    // ── Send messages ─────────────────────────────────────────────

    public function test_participant_can_send_message_to_group(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->postJson("/conversations/{$group->id}/messages", ['content' => 'Hello group!'])
            ->assertOk()
            ->assertJsonPath('message.content', 'Hello group!');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $group->id,
            'user_id'         => $m1->id,
            'content'         => 'Hello group!',
        ]);
    }

    public function test_non_participant_cannot_send_message_to_group(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group  = $this->makeGroup($owner, [$m1]);
        $outsider = $m2;

        $this->actingAs($outsider)
            ->postJson("/conversations/{$group->id}/messages", ['content' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_group_message_notifies_all_other_participants(): void
    {
        Notification::fake();

        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($owner)
            ->postJson("/conversations/{$group->id}/messages", ['content' => 'Hi all!'])
            ->assertOk();

        // m1 and m2 should be notified, owner (sender) should not
        Notification::assertSentTo($m1, MessageReceived::class);
        Notification::assertSentTo($m2, MessageReceived::class);
        Notification::assertNotSentTo($owner, MessageReceived::class);
    }

    // ── Unread count ──────────────────────────────────────────────

    public function test_unread_count_increments_for_group_members(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        // Owner sends a message
        $this->actingAs($owner)
            ->postJson("/conversations/{$group->id}/messages", ['content' => 'New message']);

        // m1 visits conversations list as themselves
        // The unread count logic is in listConversations() which uses last_read_at
        // Verify m1's last_read_at is still null (they haven't read)
        $pivot = \Illuminate\Support\Facades\DB::table('conversation_user')
            ->where('conversation_id', $group->id)
            ->where('user_id', $m1->id)
            ->first();

        $this->assertNull($pivot->last_read_at);
    }

    public function test_reading_group_marks_as_read(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        // Owner sends a message
        Message::create([
            'conversation_id' => $group->id,
            'user_id'         => $owner->id,
            'content'         => 'Read me',
        ]);

        // m1 opens the conversation (show route updates last_read_at)
        $this->actingAs($m1)
            ->get("/conversations/{$group->id}")
            ->assertOk();

        $pivot = \Illuminate\Support\Facades\DB::table('conversation_user')
            ->where('conversation_id', $group->id)
            ->where('user_id', $m1->id)
            ->first();

        $this->assertNotNull($pivot->last_read_at);
    }

    // ── Poll ──────────────────────────────────────────────────────

    public function test_poll_returns_new_group_messages(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $msg = Message::create([
            'conversation_id' => $group->id,
            'user_id'         => $owner->id,
            'content'         => 'Poll test',
        ]);

        $this->actingAs($m1)
            ->getJson("/conversations/{$group->id}/poll?after=0")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'Poll test');
    }

    public function test_poll_returns_null_other_last_read_at_for_groups(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $this->actingAs($m1)
            ->getJson("/conversations/{$group->id}/poll?after=0")
            ->assertOk()
            ->assertJsonPath('other_last_read_at', null);
    }

    // ── Edit and delete messages ──────────────────────────────────

    public function test_author_can_edit_own_group_message(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $msg = Message::create([
            'conversation_id' => $group->id,
            'user_id'         => $m1->id,
            'content'         => 'Original',
        ]);

        $this->actingAs($m1)
            ->patchJson("/messages/{$msg->id}", ['content' => 'Edited'])
            ->assertOk();

        $this->assertDatabaseHas('messages', ['id' => $msg->id, 'content' => 'Edited']);
    }

    public function test_non_author_cannot_edit_group_message(): void
    {
        [$owner, $m1, $m2] = User::factory(3)->create()->all();
        $group = $this->makeGroup($owner, [$m1, $m2]);

        $msg = Message::create([
            'conversation_id' => $group->id,
            'user_id'         => $m1->id,
            'content'         => 'Original',
        ]);

        $this->actingAs($m2)
            ->patchJson("/messages/{$msg->id}", ['content' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Direct compatibility ───────────────────────────────────────

    public function test_direct_conversation_send_still_works(): void
    {
        [$u1, $u2] = User::factory(2)->create()->all();

        $direct = Conversation::create(['type' => 'direct']);
        $direct->participants()->attach($u1->id, ['role' => 'member', 'joined_at' => now()]);
        $direct->participants()->attach($u2->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($u1)
            ->postJson("/conversations/{$direct->id}/messages", ['content' => 'Hello direct!'])
            ->assertOk()
            ->assertJsonPath('message.content', 'Hello direct!');
    }

    public function test_direct_poll_returns_other_last_read_at(): void
    {
        [$u1, $u2] = User::factory(2)->create()->all();

        $direct = Conversation::create(['type' => 'direct']);
        $direct->participants()->attach($u1->id, ['role' => 'member', 'joined_at' => now()]);
        $direct->participants()->attach($u2->id, ['role' => 'member', 'joined_at' => now(), 'last_read_at' => now()]);

        $this->actingAs($u1)
            ->getJson("/conversations/{$direct->id}/poll?after=0")
            ->assertOk()
            ->assertJsonStructure(['other_last_read_at']);
    }
}
