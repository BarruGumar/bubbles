<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeReport(User $reporter): Report
    {
        $post = Post::create(['user_id' => $reporter->id, 'content' => 'Reported post']);

        return Report::create([
            'reporter_id'     => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id'   => $post->id,
            'reason'          => 'Violates rules',
            'status'          => 'pending',
        ]);
    }

    // ---------------------------------------------------------------
    // Access control
    // ---------------------------------------------------------------

    public function test_guest_cannot_access_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_moderator_cannot_access_admin_panel(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_admin_can_view_reports(): void
    {
        $admin    = $this->admin();
        $reporter = User::factory()->create();
        $this->makeReport($reporter);

        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    }

    // ---------------------------------------------------------------
    // Report resolution
    // ---------------------------------------------------------------

    public function test_admin_can_resolve_report(): void
    {
        $admin    = $this->admin();
        $reporter = User::factory()->create();
        $report   = $this->makeReport($reporter);

        $this->actingAs($admin)
            ->patch("/admin/reports/{$report->id}/resolve", ['admin_note' => 'Reviewed'])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'resolved']);
    }

    public function test_admin_can_dismiss_report(): void
    {
        $admin    = $this->admin();
        $reporter = User::factory()->create();
        $report   = $this->makeReport($reporter);

        $this->actingAs($admin)
            ->patch("/admin/reports/{$report->id}/dismiss", [])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'dismissed']);
    }

    // ---------------------------------------------------------------
    // User management
    // ---------------------------------------------------------------

    public function test_admin_can_change_another_users_role(): void
    {
        $admin  = $this->admin();
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/role", ['role' => 'moderator'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'moderator']);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertRedirect();

        // Role must remain admin
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin  = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    // ---------------------------------------------------------------
    // Post management
    // ---------------------------------------------------------------

    public function test_admin_can_force_delete_post(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();
        $post  = Post::create(['user_id' => $user->id, 'content' => 'Bad post']);

        $this->actingAs($admin)
            ->delete("/admin/posts/{$post->id}/force")
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_admin_can_restore_soft_deleted_post(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();
        $post  = Post::create(['user_id' => $user->id, 'content' => 'Deleted post']);
        $post->delete();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);

        $this->actingAs($admin)
            ->post("/admin/posts/{$post->id}/restore")
            ->assertRedirect();

        $this->assertNotSoftDeleted('posts', ['id' => $post->id]);
    }
}
