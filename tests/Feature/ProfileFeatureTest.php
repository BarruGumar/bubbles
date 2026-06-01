<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    // ── Access ─────────────────────────────────────────────────────

    public function test_guest_can_view_profile_page(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $this->get('/u/testuser')->assertOk();
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create(['username' => 'selfuser']);
        $this->actingAs($user)->get('/u/selfuser')->assertOk();
    }

    public function test_authenticated_user_can_view_other_profile(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create(['username' => 'target']);
        $this->actingAs($viewer)->get('/u/target')->assertOk();
    }

    public function test_profile_returns_404_for_unknown_username(): void
    {
        $this->get('/u/nonexistent_xyz999')->assertNotFound();
    }

    // ── Cursor pagination ──────────────────────────────────────────

    public function test_profile_returns_has_more_when_posts_exceed_12(): void
    {
        $user = User::factory()->create(['username' => 'paguser']);

        for ($i = 1; $i <= 13; $i++) {
            Post::create(['user_id' => $user->id, 'content' => "Post {$i}"]);
        }

        $this->actingAs($user)
            ->get('/u/paguser')
            ->assertInertia(fn ($page) => $page
                ->has('posts', 12)
                ->where('hasMorePosts', true)
                ->whereNot('nextCursor', null)
            );
    }

    public function test_profile_has_more_posts_false_when_12_or_fewer(): void
    {
        $user = User::factory()->create(['username' => 'smalluser']);

        for ($i = 1; $i <= 5; $i++) {
            Post::create(['user_id' => $user->id, 'content' => "Post {$i}"]);
        }

        $this->actingAs($user)
            ->get('/u/smalluser')
            ->assertInertia(fn ($page) => $page
                ->has('posts', 5)
                ->where('hasMorePosts', false)
                ->where('nextCursor', null)
            );
    }

    public function test_profile_cursor_loads_second_page(): void
    {
        $user = User::factory()->create(['username' => 'cursoruser']);

        // Post::factory used because created_at is not in $fillable — factory bypasses this
        for ($i = 1; $i <= 15; $i++) {
            Post::factory()->create([
                'user_id'    => $user->id,
                'content'    => "Post {$i}",
                'created_at' => now()->subSeconds($i),
                'updated_at' => now()->subSeconds($i),
            ]);
        }

        // First page: 12 newest posts; extract cursor
        $nextCursor = $this->actingAs($user)
            ->get('/u/cursoruser')
            ->inertiaProps('nextCursor');

        $this->assertNotNull($nextCursor);

        // Second page: remaining 3 posts
        $this->actingAs($user)
            ->get("/u/cursoruser?cursor={$nextCursor}")
            ->assertInertia(fn ($page) => $page
                ->has('posts', 3)
                ->where('hasMorePosts', false)
                ->where('nextCursor', null)
            );
    }
}
