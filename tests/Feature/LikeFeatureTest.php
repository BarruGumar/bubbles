<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'content' => 'Test']);
    }

    public function test_guest_cannot_like_post(): void
    {
        $owner = User::factory()->create();
        $post = $this->makePost($owner);

        $this->post("/posts/{$post->id}/like")->assertRedirect('/login');

        $this->assertDatabaseEmpty('likes');
    }

    public function test_user_can_like_a_post(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $post = $this->makePost($owner);

        $this->actingAs($liker)->post("/posts/{$post->id}/like")->assertRedirect();

        $this->assertDatabaseHas('likes', [
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
            'user_id' => $liker->id,
            'type' => 'like',
        ]);
    }

    public function test_liking_same_type_again_removes_the_like(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $post = $this->makePost($owner);

        $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => 'like']);
        $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => 'like']);

        $this->assertDatabaseEmpty('likes');
    }

    public function test_liking_with_different_type_changes_reaction(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $post = $this->makePost($owner);

        $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => 'like']);
        $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => 'love']);

        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseHas('likes', ['user_id' => $liker->id, 'type' => 'love']);
    }

    public function test_invalid_reaction_type_defaults_to_like(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $post = $this->makePost($owner);

        $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => 'dislike']);

        $this->assertDatabaseHas('likes', ['user_id' => $liker->id, 'type' => 'like']);
    }

    public function test_all_valid_reaction_types_are_accepted(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $post = $this->makePost($owner);

        foreach (['like', 'love', 'laugh', 'wow', 'sad'] as $type) {
            // Remove existing like first by sending same type (toggle)
            if ($liker->id) {
                $post->likes()->where('user_id', $liker->id)->delete();
            }
            $this->actingAs($liker)->post("/posts/{$post->id}/like", ['type' => $type]);
            $this->assertDatabaseHas('likes', ['user_id' => $liker->id, 'type' => $type]);
        }
    }
}
