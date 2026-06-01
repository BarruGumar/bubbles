<?php

namespace Tests\Feature;

use App\Models\Friend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_feed(): void
    {
        $this->get('/feed')->assertRedirect('/login');
    }

    public function test_guest_cannot_view_bubbles_home(): void
    {
        $this->get('/bubbles')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_feed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/feed')->assertOk();
    }

    public function test_authenticated_user_can_view_bubbles_home(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/bubbles')->assertOk();
    }

    public function test_feed_loads_when_user_has_friends_with_posts(): void
    {
        $user   = User::factory()->create();
        $friend = User::factory()->create();

        Friend::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);
        Post::create(['user_id' => $friend->id, 'content' => 'Hello from friend']);

        // If the friend-posts query is broken, this would 500 instead of 200
        $this->actingAs($user)->get('/feed')->assertOk();
    }

    public function test_feed_loads_when_user_has_no_friends(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Post::create(['user_id' => $other->id, 'content' => 'Public post']);

        // Falls back to recent posts — should not 500
        $this->actingAs($user)->get('/feed')->assertOk();
    }

    public function test_banned_user_cannot_view_feed(): void
    {
        $banned = User::factory()->create(['role' => 'banned']);
        $this->actingAs($banned)->get('/feed')->assertRedirect('/');
    }
}
