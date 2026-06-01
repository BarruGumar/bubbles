<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_renders(): void
    {
        $this->get('/search')->assertOk();
    }

    public function test_api_search_returns_empty_for_short_query(): void
    {
        $this->getJson('/api/search?q=a')
            ->assertOk()
            ->assertJson(['users' => [], 'communities' => [], 'posts' => []]);
    }

    public function test_api_search_returns_empty_without_query(): void
    {
        $this->getJson('/api/search')
            ->assertOk()
            ->assertJson(['users' => [], 'communities' => [], 'posts' => []]);
    }

    public function test_api_search_finds_user_by_name(): void
    {
        User::factory()->create(['name' => 'Alice Wonderland', 'username' => 'alice_w']);

        $this->getJson('/api/search?q=alice')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Alice Wonderland']);
    }

    public function test_api_search_finds_user_by_username(): void
    {
        User::factory()->create(['name' => 'Bob Smith', 'username' => 'bobsmith99']);

        $this->getJson('/api/search?q=bobsmith')
            ->assertOk()
            ->assertJsonFragment(['username' => 'bobsmith99']);
    }

    public function test_api_search_finds_post_by_content(): void
    {
        $user = User::factory()->create();
        Post::create(['user_id' => $user->id, 'content' => 'Laravel is amazing']);

        $this->getJson('/api/search?q=laravel')
            ->assertOk()
            ->assertJsonFragment(['content' => 'Laravel is amazing']);
    }

    public function test_api_search_does_not_return_deleted_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::create(['user_id' => $user->id, 'content' => 'Deleted post content']);
        $post->delete();

        $response = $this->getJson('/api/search?q=deleted')
            ->assertOk();

        $this->assertEmpty($response->json('posts'));
    }

    public function test_search_result_does_not_expose_password(): void
    {
        User::factory()->create(['name' => 'Test Person', 'username' => 'testperson']);

        $response = $this->getJson('/api/search?q=testperson')->assertOk();

        $users = $response->json('users');
        $this->assertNotEmpty($users);
        $this->assertArrayNotHasKey('password', $users[0]);
    }
}
