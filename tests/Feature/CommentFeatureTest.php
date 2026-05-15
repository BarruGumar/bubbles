<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'content' => 'Post']);
    }

    private function makeComment(Post $post, User $user, string $content = 'A comment'): Comment
    {
        return $post->comments()->create(['user_id' => $user->id, 'content' => $content]);
    }

    // ---------------------------------------------------------------
    // POST /posts/{post}/comments
    // ---------------------------------------------------------------

    public function test_guest_cannot_comment_on_post(): void
    {
        $owner = User::factory()->create();
        $post  = $this->makePost($owner);

        $this->post("/posts/{$post->id}/comments", ['content' => 'Hello'])->assertRedirect('/login');

        $this->assertDatabaseEmpty('comments');
    }

    public function test_user_can_comment_on_post(): void
    {
        $owner     = User::factory()->create();
        $commenter = User::factory()->create();
        $post      = $this->makePost($owner);

        $this->actingAs($commenter)
            ->post("/posts/{$post->id}/comments", ['content' => 'Great post!'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'commentable_type' => Post::class,
            'commentable_id'   => $post->id,
            'user_id'          => $commenter->id,
            'content'          => 'Great post!',
        ]);
    }

    public function test_comment_content_is_required(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseEmpty('comments');
    }

    public function test_comment_content_max_500_chars(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => str_repeat('x', 501)])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseEmpty('comments');
    }

    // ---------------------------------------------------------------
    // DELETE /comments/{comment}
    // ---------------------------------------------------------------

    public function test_user_can_delete_own_comment(): void
    {
        $user    = User::factory()->create();
        $post    = $this->makePost($user);
        $comment = $this->makeComment($post, $user);

        $this->actingAs($user)->delete("/comments/{$comment->id}")->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_non_owner_cannot_delete_comment(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $post     = $this->makePost($owner);
        $comment  = $this->makeComment($post, $owner);

        $this->actingAs($attacker)->delete("/comments/{$comment->id}")->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }
}
