<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginAccessMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_turns_login_on_only_for_people_with_a_login(): void
    {
        $migration = require database_path('migrations/2026_09_29_000002_add_login_access_to_people_and_users.php');
        $migration->down();

        $withLogin = DB::table('people')->insertGetId([
            'name' => 'With Login',
            'email' => 'with-login@example.test',
        ]);
        $withoutLogin = DB::table('people')->insertGetId([
            'name' => 'Without Login',
            'email' => 'without-login@example.test',
        ]);
        DB::table('users')->insert([
            'name' => 'With Login',
            'email' => 'with-login@example.test',
            'password' => 'x',
            'person_id' => $withLogin,
        ]);

        $migration->up();

        $this->assertTrue((bool) DB::table('people')->where('id', $withLogin)->value('can_log_in'));
        $this->assertFalse((bool) DB::table('people')->where('id', $withoutLogin)->value('can_log_in'));
        $this->assertFalse((bool) DB::table('users')->where('person_id', $withLogin)->value('is_admin'));
    }

    public function test_new_login_starts_with_login_enabled_and_admin_off(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->person->can_log_in);
        $this->assertFalse($user->fresh()->is_admin);
    }
}
