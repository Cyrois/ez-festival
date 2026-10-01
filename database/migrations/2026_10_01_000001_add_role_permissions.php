<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->json('permissions')->default('[]'));
        DB::table('roles')->where('can_read_team_notes', true)->update(['permissions' => json_encode(['team.notes.read'])]);
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('can_read_team_notes'));
    }

    public function down(): void
    {
        // Permission assignments cannot be represented by the retired boolean.
    }
};
