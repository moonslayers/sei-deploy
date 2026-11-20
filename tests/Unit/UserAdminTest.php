<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_in_allowlist_can_access_panel(): void
    {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $this->assertTrue($user->isAdmin());
    }

    public function test_email_outside_allowlist_cannot_access_panel(): void
    {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'other@example.com']);
        $this->assertFalse($user->isAdmin());
    }

    public function test_empty_allowlist_means_no_access(): void
    {
        config(['admin.emails' => []]);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $this->assertFalse($user->isAdmin());
    }

    public function test_comparison_is_case_insensitive(): void
    {
        config(['admin.emails' => ['Admin@Example.com']]);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $this->assertTrue($user->isAdmin());
    }
}
