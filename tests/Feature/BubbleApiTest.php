<?php

namespace Tests\Feature;

use App\Models\Bubble;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BubbleApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function bubblePayload(array $overrides = []): array
    {
        return array_merge([
            'label' => '#test',
            'color' => '#009ac7',
            'x' => 200,
            'y' => 300,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // POST /api/bubbles
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_create_bubble(): void
    {
        $response = $this->postJson('/api/bubbles', $this->bubblePayload());

        $response->assertStatus(401);
        $this->assertDatabaseEmpty('bubbles');
    }

    public function test_authenticated_user_creates_bubble_with_own_user_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bubbles', $this->bubblePayload());

        $response->assertStatus(201)->assertJsonStructure(['id']);

        $this->assertDatabaseHas('bubbles', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'label' => '#test',
        ]);
    }

    public function test_authenticated_user_cannot_force_another_user_id_on_bubble(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $payload = $this->bubblePayload(['user_id' => $owner->id]);

        $response = $this->actingAs($attacker)->postJson('/api/bubbles', $payload);

        $response->assertStatus(201);

        // Bubble must belong to the attacker, not the target user
        $this->assertDatabaseHas('bubbles', [
            'id' => $response->json('id'),
            'user_id' => $attacker->id,
        ]);
        $this->assertDatabaseMissing('bubbles', [
            'id' => $response->json('id'),
            'user_id' => $owner->id,
        ]);
    }

    public function test_bubble_creator_is_added_as_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bubbles', $this->bubblePayload());

        $response->assertStatus(201);
        $bubbleId = $response->json('id');

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubbleId,
            'user_id' => $user->id,
        ]);
    }

    // ---------------------------------------------------------------
    // PATCH /api/bubbles/{bubble}
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_update_bubble(): void
    {
        $bubble = Bubble::factory()->create();

        $this->patchJson("/api/bubbles/{$bubble->id}", ['label' => '#hacked'])
            ->assertStatus(401);
    }

    public function test_owner_can_update_own_bubble(): void
    {
        $user = User::factory()->create();
        $bubble = Bubble::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson("/api/bubbles/{$bubble->id}", ['label' => '#updated'])
            ->assertStatus(200);

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id, 'label' => '#updated']);
    }

    public function test_non_owner_cannot_update_bubble(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $bubble = Bubble::factory()->for($owner)->create(['label' => '#original']);

        $this->actingAs($attacker)
            ->patchJson("/api/bubbles/{$bubble->id}", ['label' => '#hacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id, 'label' => '#original']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/bubbles/{bubble}
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_delete_bubble(): void
    {
        $bubble = Bubble::factory()->create();

        $this->deleteJson("/api/bubbles/{$bubble->id}")->assertStatus(401);
        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id]);
    }

    public function test_owner_can_delete_own_bubble(): void
    {
        $user = User::factory()->create();
        $bubble = Bubble::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/bubbles/{$bubble->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('bubbles', ['id' => $bubble->id]);
    }

    public function test_non_owner_cannot_delete_bubble(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $bubble = Bubble::factory()->for($owner)->create();

        $this->actingAs($attacker)
            ->deleteJson("/api/bubbles/{$bubble->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id]);
    }

    // ---------------------------------------------------------------
    // POST /api/connections
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_create_connection(): void
    {
        $a = Bubble::factory()->create();
        $b = Bubble::factory()->create();

        $this->postJson('/api/connections', [
            'from_bubble_id' => $a->id,
            'to_bubble_id' => $b->id,
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_create_connection(): void
    {
        $user = User::factory()->create();
        $a = Bubble::factory()->create();
        $b = Bubble::factory()->create();

        $this->actingAs($user)->postJson('/api/connections', [
            'from_bubble_id' => $a->id,
            'to_bubble_id' => $b->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('connections', [
            'from_bubble_id' => $a->id,
            'to_bubble_id' => $b->id,
        ]);
    }

    // ---------------------------------------------------------------
    // DELETE /api/connections/{connection}
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_delete_connection(): void
    {
        $owner = User::factory()->create();
        $a = Bubble::factory()->for($owner)->create();
        $b = Bubble::factory()->create();
        $conn = Connection::create(['from_bubble_id' => $a->id, 'to_bubble_id' => $b->id]);

        $this->deleteJson("/api/connections/{$conn->id}")->assertStatus(401);
        $this->assertDatabaseHas('connections', ['id' => $conn->id]);
    }

    public function test_owner_of_from_bubble_can_delete_connection(): void
    {
        $owner = User::factory()->create();
        $a = Bubble::factory()->for($owner)->create();
        $b = Bubble::factory()->create();
        $conn = Connection::create(['from_bubble_id' => $a->id, 'to_bubble_id' => $b->id]);

        $this->actingAs($owner)
            ->deleteJson("/api/connections/{$conn->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('connections', ['id' => $conn->id]);
    }

    public function test_non_owner_cannot_delete_connection(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $a = Bubble::factory()->for($owner)->create();
        $b = Bubble::factory()->for($owner)->create();
        $conn = Connection::create(['from_bubble_id' => $a->id, 'to_bubble_id' => $b->id]);

        $this->actingAs($attacker)
            ->deleteJson("/api/connections/{$conn->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('connections', ['id' => $conn->id]);
    }
}
