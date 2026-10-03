<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginAccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_access_and_admin_defaults_are_present_in_the_fresh_schema(): void
    {
        $personId = DB::table('people')->insertGetId([
            'name' => 'Without Login',
            'email' => 'without-login@example.test',
        ]);
        DB::table('users')->insert([
            'name' => 'Pending invitation',
            'email' => 'pending@example.test',
            'person_id' => $personId,
        ]);

        $this->assertFalse((bool) DB::table('people')->where('id', $personId)->value('can_log_in'));
        $user = DB::table('users')->where('person_id', $personId)->sole();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertTrue((bool) $user->has_set_password);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertNull($user->password);
    }

    public function test_new_login_starts_with_login_enabled_and_admin_off(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->person->can_log_in);
        $this->assertFalse($user->fresh()->is_admin);
    }
}
