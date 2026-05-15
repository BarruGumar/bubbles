<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'content' => 'Reportable post']);
    }

    public function test_guest_cannot_report_post(): void
    {
        $owner = User::factory()->create();
        $post  = $this->makePost($owner);

        $this->post("/posts/{$post->id}/report", ['reason' => 'Spam content'])
            ->assertRedirect('/login');

        $this->assertDatabaseEmpty('reports');
    }

    public function test_user_can_report_post(): void
    {
        $owner    = User::factory()->create();
        $reporter = User::factory()->create();
        $post     = $this->makePost($owner);

        $this->actingAs($reporter)
            ->postJson("/posts/{$post->id}/report", ['reason' => 'This is spam content'])
            ->assertOk()
            ->assertJson(['message' => 'Denúncia enviada.']);

        $this->assertDatabaseHas('reports', [
            'reporter_id'     => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id'   => $post->id,
            'status'          => 'pending',
        ]);
    }

    public function test_report_reason_is_required(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->postJson("/posts/{$post->id}/report", ['reason' => ''])
            ->assertUnprocessable();

        $this->assertDatabaseEmpty('reports');
    }

    public function test_report_reason_must_be_at_least_5_chars(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->postJson("/posts/{$post->id}/report", ['reason' => 'bad'])
            ->assertUnprocessable();

        $this->assertDatabaseEmpty('reports');
    }

    public function test_report_reason_cannot_exceed_500_chars(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $this->actingAs($user)
            ->postJson("/posts/{$post->id}/report", ['reason' => str_repeat('a', 501)])
            ->assertUnprocessable();

        $this->assertDatabaseEmpty('reports');
    }

    public function test_reporting_same_post_twice_updates_the_reason(): void
    {
        $reporter = User::factory()->create();
        $owner    = User::factory()->create();
        $post     = $this->makePost($owner);

        $this->actingAs($reporter)->postJson("/posts/{$post->id}/report", ['reason' => 'First reason here']);
        $this->actingAs($reporter)->postJson("/posts/{$post->id}/report", ['reason' => 'Updated reason here']);

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['reason' => 'Updated reason here']);
    }
}
