<?php

namespace Tests\Feature;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Friend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    // ── Cursor pagination ──────────────────────────────────────────

    public function test_feed_returns_has_more_and_next_cursor_when_20_items_available(): void
    {
        Cache::flush();

        $user   = User::factory()->create();
        $friend = User::factory()->create();
        Friend::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        // Fill the friend-posts bucket (limit 15)
        for ($i = 0; $i < 15; $i++) {
            Post::create(['user_id' => $friend->id, 'content' => "Friend post {$i}"]);
        }

        // Community owned by user is auto-included in communityIds; fill its bucket (limit 15)
        $bubble = Bubble::factory()->create(['user_id' => $user->id]);
        for ($i = 0; $i < 15; $i++) {
            CommunityPost::create(['bubble_id' => $bubble->id, 'user_id' => $friend->id, 'content' => "Community post {$i}"]);
        }

        $this->actingAs($user)
            ->get('/feed')
            ->assertInertia(fn ($page) => $page
                ->has('feed', 20)
                ->where('hasMore', true)
                ->whereNot('nextCursor', null)
            );
    }

    public function test_feed_cursor_excludes_posts_not_older_than_cursor(): void
    {
        Cache::flush();

        $user   = User::factory()->create();
        $friend = User::factory()->create();
        Friend::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $cutoff = now()->subHour();

        // 4 posts before the cutoff — should be returned
        // (Post::factory used because created_at is not in $fillable — factory bypasses this)
        for ($i = 1; $i <= 4; $i++) {
            Post::factory()->create([
                'user_id'    => $friend->id,
                'content'    => "Old post {$i}",
                'created_at' => $cutoff->copy()->subMinutes($i),
                'updated_at' => $cutoff->copy()->subMinutes($i),
            ]);
        }

        // 3 posts after the cutoff — must be excluded by the cursor filter
        for ($i = 1; $i <= 3; $i++) {
            Post::factory()->create([
                'user_id'    => $friend->id,
                'content'    => "New post {$i}",
                'created_at' => $cutoff->copy()->addMinutes($i),
                'updated_at' => $cutoff->copy()->addMinutes($i),
            ]);
        }

        // cursor = Unix timestamp of cutoff → WHERE created_at < cutoff
        $cursor = $cutoff->timestamp;

        $this->actingAs($user)
            ->get("/feed?cursor={$cursor}")
            ->assertInertia(fn ($page) => $page
                ->has('feed', 4)
                ->where('hasMore', false)
                ->where('nextCursor', null)
            );
    }

    public function test_feed_has_more_false_when_fewer_than_20_items(): void
    {
        Cache::flush();

        $user   = User::factory()->create();
        $friend = User::factory()->create();
        Friend::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        for ($i = 0; $i < 6; $i++) {
            Post::create(['user_id' => $friend->id, 'content' => "Post {$i}"]);
        }

        $this->actingAs($user)
            ->get('/feed')
            ->assertInertia(fn ($page) => $page
                ->has('feed', 6)
                ->where('hasMore', false)
                ->where('nextCursor', null)
            );
    }
}
