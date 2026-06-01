<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id'               => (string) Str::uuid(),
            'type'             => 'App\\Notifications\\FriendRequestReceived',
            'notifiable_type'  => 'App\\Models\\User',
            'notifiable_id'    => $user->id,
            'data'             => json_encode(['message' => 'Test notification']),
            'read_at'          => null,
        ]);
    }

    // ── Index ──────────────────────────────────────────────────────

    public function test_guest_cannot_view_notifications(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_notifications_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/notifications')->assertOk();
    }

    // ── markRead ───────────────────────────────────────────────────

    public function test_user_can_mark_notification_as_read(): void
    {
        $user         = User::factory()->create();
        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    // ── markAllRead ────────────────────────────────────────────────

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user);

        $this->actingAs($user)
            ->patch('/notifications/read-all')
            ->assertRedirect();

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    // ── destroy ────────────────────────────────────────────────────

    public function test_user_can_delete_single_notification(): void
    {
        $user         = User::factory()->create();
        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->delete("/notifications/{$notification->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    // ── destroyAll ─────────────────────────────────────────────────

    public function test_user_can_delete_all_notifications(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user);

        $this->actingAs($user)
            ->delete('/notifications')
            ->assertRedirect();

        $this->assertEquals(0, $user->notifications()->count());
    }

    public function test_notifications_of_other_users_are_not_deleted(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $this->createNotification($other);

        $this->actingAs($user)->delete('/notifications');

        $this->assertEquals(1, $other->notifications()->count());
    }
}
