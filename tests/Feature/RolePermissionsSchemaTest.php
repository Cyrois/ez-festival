<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RolePermissionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_roles_default_to_empty_permissions_without_the_retired_column(): void
    {
        DB::table('roles')->insert(['name' => 'Crew', 'name_key' => 'crew']);

        $this->assertFalse(Schema::hasColumn('roles', 'can_read_team_notes'));
        $this->assertSame([], json_decode(DB::table('roles')->where('name_key', 'crew')->value('permissions'), true));
    }
}
