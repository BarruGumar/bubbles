<?php

namespace Tests\Feature;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCommunity(User $owner, array $attrs = []): Bubble
    {
        $bubble = Bubble::factory()->for($owner)->create($attrs);
        // Creator is automatically a member (mirrors BubbleController::store)
        $bubble->memberships()->attach($owner->id);

        return $bubble;
    }

    // ---------------------------------------------------------------
    // POST /c/{id}/join  |  DELETE /c/{id}/leave
    // ---------------------------------------------------------------

    public function test_user_can_join_community(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($member)->post("/c/{$bubble->id}/join")->assertRedirect();

        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_user_can_leave_community(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $bubble->memberships()->attach($member->id);

        $this->actingAs($member)->delete("/c/{$bubble->id}/leave")->assertRedirect();

        $this->assertDatabaseMissing('community_user', [
            'community_id' => $bubble->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_creator_cannot_leave_own_community(): void
    {
        $owner = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($owner)->delete("/c/{$bubble->id}/leave")->assertRedirect();

        // Membership must still exist
        $this->assertDatabaseHas('community_user', [
            'community_id' => $bubble->id,
            'user_id' => $owner->id,
        ]);
    }

    // ---------------------------------------------------------------
    // POST /c/{id}/posts
    // ---------------------------------------------------------------

    public function test_guest_cannot_create_community_post(): void
    {
        $owner = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->post("/c/{$bubble->id}/posts", ['content' => 'Hello'])->assertRedirect('/login');

        $this->assertDatabaseEmpty('community_posts');
    }

    public function test_member_can_create_community_post(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $bubble->memberships()->attach($member->id);

        $this->actingAs($member)
            ->post("/c/{$bubble->id}/posts", ['content' => 'Hello community'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_posts', [
            'bubble_id' => $bubble->id,
            'user_id' => $member->id,
            'content' => 'Hello community',
        ]);
    }

    public function test_non_member_cannot_create_community_post(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($visitor)
            ->post("/c/{$bubble->id}/posts", ['content' => 'Intruder post'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('community_posts');
    }

    // ---------------------------------------------------------------
    // PATCH /c/{id}/posts/{post}
    // ---------------------------------------------------------------

    public function test_owner_can_update_own_community_post(): void
    {
        $owner = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $post = CommunityPost::create([
            'bubble_id' => $bubble->id,
            'user_id' => $owner->id,
            'content' => 'Original',
        ]);

        $this->actingAs($owner)
            ->patchJson("/c/{$bubble->id}/posts/{$post->id}", ['content' => 'Updated'])
            ->assertOk()
            ->assertJson(['content' => 'Updated']);

        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'content' => 'Updated']);
    }

    public function test_non_owner_cannot_update_community_post(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $bubble = $this->makeCommunity($owner);
        $bubble->memberships()->attach($attacker->id);
        $post = CommunityPost::create([
            'bubble_id' => $bubble->id,
            'user_id' => $owner->id,
            'content' => 'Original',
        ]);

        $this->actingAs($attacker)
            ->patchJson("/c/{$bubble->id}/posts/{$post->id}", ['content' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'content' => 'Original']);
    }

    // ---------------------------------------------------------------
    // PUT /c/{id}/settings
    // ---------------------------------------------------------------

    public function test_community_owner_can_update_settings(): void
    {
        $owner = User::factory()->create();
        $bubble = $this->makeCommunity($owner, ['label' => '#original']);

        $this->actingAs($owner)->put("/c/{$bubble->id}/settings", [
            'label' => '#updated',
        ])->assertRedirect();

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id, 'label' => '#updated']);
    }

    public function test_non_owner_cannot_update_community_settings(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $bubble = $this->makeCommunity($owner, ['label' => '#original']);

        $this->actingAs($attacker)->put("/c/{$bubble->id}/settings", [
            'label' => '#hacked',
        ])->assertForbidden();

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id, 'label' => '#original']);
    }

    // ---------------------------------------------------------------
    // DELETE /c/{id}
    // ---------------------------------------------------------------

    public function test_owner_can_delete_community(): void
    {
        $owner = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($owner)->delete("/c/{$bubble->id}")->assertRedirect();

        $this->assertDatabaseMissing('bubbles', ['id' => $bubble->id]);
    }

    public function test_non_owner_cannot_delete_community(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $bubble = $this->makeCommunity($owner);

        $this->actingAs($attacker)->delete("/c/{$bubble->id}")->assertForbidden();

        $this->assertDatabaseHas('bubbles', ['id' => $bubble->id]);
    }
}
