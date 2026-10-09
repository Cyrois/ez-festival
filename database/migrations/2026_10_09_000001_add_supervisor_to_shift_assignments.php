<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->boolean('is_supervisor')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX shift_assignments_supervisor_unique ON shift_assignments (shift_id) WHERE is_supervisor = TRUE');
    }

    public function down(): void
    {
        if (Schema::hasColumn('shift_assignments', 'is_supervisor')) {
            // Rolling back discards supervisor selections, but preserves assignments.
            DB::statement('DROP INDEX IF EXISTS shift_assignments_supervisor_unique');
            Schema::table('shift_assignments', function (Blueprint $table): void {
                $table->dropColumn('is_supervisor');
            });
        }
    }
};
