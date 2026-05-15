<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_welcome_page(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_authenticated_user_is_redirected_from_root_to_bubbles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect('/bubbles');
    }
}
