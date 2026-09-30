<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('can_read_team_notes')->default(false)->after('active');
        });

        Schema::table('team_engagement_notes', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('team_engagement_notes', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('can_read_team_notes');
        });
    }
};
