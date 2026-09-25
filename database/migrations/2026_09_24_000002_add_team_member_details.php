<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('team_engagements', function (Blueprint $table) {
            $table->string('role_title')->nullable()->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('team_engagements', function (Blueprint $table) {
            $table->dropColumn('role_title');
        });

        // People created without an email cannot be safely remapped on rollback.
    }
};
