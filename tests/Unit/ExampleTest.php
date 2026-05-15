<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_admin_is_admin_and_moderator_but_not_suspended(): void
    {
        $user = new User(['role' => 'admin']);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isModerator());
        $this->assertFalse($user->isSuspended());
    }

    public function test_moderator_is_not_admin_but_is_moderator(): void
    {
        $user = new User(['role' => 'moderator']);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isModerator());
        $this->assertFalse($user->isSuspended());
    }

    public function test_regular_user_has_no_elevated_permissions(): void
    {
        $user = new User(['role' => 'user']);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isModerator());
        $this->assertFalse($user->isSuspended());
    }

    public function test_suspended_user_is_suspended_and_not_admin(): void
    {
        $user = new User(['role' => 'suspended']);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isModerator());
        $this->assertTrue($user->isSuspended());
    }
}
