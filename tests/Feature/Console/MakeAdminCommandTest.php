<?php

namespace Tests\Feature\Console;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_login_is_made_admin_with_login_enabled_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->person->update(['can_log_in' => false]);

        $this->artisan('app:make-admin', ['email' => '  ADMIN@EXAMPLE.TEST  '])
            ->expectsOutput(__('commands.make_admin.success', ['email' => 'admin@example.test']))
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
        $this->assertTrue($user->person->fresh()->can_log_in);
    }

    public function test_missing_login_fails_without_creating_or_changing_anything(): void
    {
        $existing = User::factory()->create();
        $usersBefore = User::query()->count();
        $peopleBefore = Person::query()->count();

        $this->artisan('app:make-admin', ['email' => ' missing@example.test '])
            ->expectsOutput(__('commands.make_admin.not_found', ['email' => 'missing@example.test']))
            ->assertFailed();

        $this->assertDatabaseCount('users', $usersBefore);
        $this->assertDatabaseCount('people', $peopleBefore);
        $this->assertFalse($existing->fresh()->is_admin);
    }

    public function test_command_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->artisan('app:make-admin', ['email' => $user->email])->assertSuccessful();
        $firstUpdatedAt = $user->fresh()->updated_at;

        $this->artisan('app:make-admin', ['email' => $user->email])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
        $this->assertTrue($user->person->fresh()->can_log_in);
        $this->assertTrue($firstUpdatedAt->equalTo($user->fresh()->updated_at));
    }
}
