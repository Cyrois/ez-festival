<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_engagements', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // Adding a foreign key rebuilds the SQLite table and drops the enum check
                // constraints Laravel created for status and employment_type.
                $table->unsignedBigInteger('role_id')->nullable()->index();
            } else {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_engagements', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropColumn('role_id');
            } else {
                $table->dropConstrainedForeignId('role_id');
            }
        });
    }
};
