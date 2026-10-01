<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RolePermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_note_read_permission_is_preserved_and_retired_column_is_removed(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('permissions');
            $table->boolean('can_read_team_notes')->default(false);
        });
        DB::table('roles')->insert([
            ['name' => 'Notes reader', 'name_key' => 'notes reader', 'can_read_team_notes' => true],
            ['name' => 'Other', 'name_key' => 'other', 'can_read_team_notes' => false],
        ]);
        $migration = require database_path('migrations/2026_10_01_000001_add_role_permissions.php');
        $migration->up();
        $this->assertFalse(Schema::hasColumn('roles', 'can_read_team_notes'));
        $this->assertSame(['team.notes.read'], json_decode(DB::table('roles')->where('name_key', 'notes reader')->value('permissions'), true));
        $this->assertSame([], json_decode(DB::table('roles')->where('name_key', 'other')->value('permissions'), true));
    }
}
