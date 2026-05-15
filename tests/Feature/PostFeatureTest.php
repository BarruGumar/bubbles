<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user, array $attrs = []): Post
    {
        return Post::create(array_merge(['user_id' => $user->id, 'content' => 'Test post'], $attrs));
    }

    // ---------------------------------------------------------------
    // POST /posts
    // ---------------------------------------------------------------

    public function test_guest_cannot_create_post(): void
    {
        $this->post('/posts', ['content' => 'Hello'])->assertRedirect('/login');

        $this->assertDatabaseEmpty('posts');
    }

    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => 'Hello world'])->assertRedirect();

        $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'content' => 'Hello world']);
    }

    public function test_post_content_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseEmpty('posts');
    }

    public function test_post_content_cannot_exceed_1000_chars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseEmpty('posts');
    }

    // ---------------------------------------------------------------
    // PATCH /posts/{post}
    // ---------------------------------------------------------------

    public function test_owner_can_update_own_post(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->patchJson("/posts/{$post->id}", ['content' => 'Updated content'])
            ->assertOk()
            ->assertJson(['content' => 'Updated content']);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'content' => 'Updated content']);
    }

    public function test_non_owner_cannot_update_post(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $post = $this->makePost($owner, ['content' => 'Original']);

        $this->actingAs($attacker)
            ->patchJson("/posts/{$post->id}", ['content' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'content' => 'Original']);
    }

    public function test_update_requires_content(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->patchJson("/posts/{$post->id}", ['content' => ''])
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // DELETE /posts/{post}
    // ---------------------------------------------------------------

    public function test_owner_can_delete_own_post(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)->delete("/posts/{$post->id}")->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_non_owner_cannot_delete_post(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $post = $this->makePost($owner);

        $this->actingAs($attacker)->delete("/posts/{$post->id}")->assertForbidden();

        $this->assertNotSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_moderator_can_delete_any_post(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $post = $this->makePost($owner);

        $this->actingAs($moderator)->delete("/posts/{$post->id}")->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
