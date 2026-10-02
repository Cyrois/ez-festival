<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX shift_slots_supervisor_unique');
        Schema::table('shift_role_slots', function (Blueprint $table): void {
            $table->dropIndex('shift_slots_order_idx');
            $table->dropColumn('is_supervisor');
        });
        Schema::table('shift_role_slots', function (Blueprint $table): void {
            $table->index(['shift_id', 'sort_order', 'id'], 'shift_slots_order_idx');
        });
    }

    public function down(): void
    {
        // Removed selections cannot be reconstructed; restored flags default to false.
        Schema::table('shift_role_slots', function (Blueprint $table): void {
            $table->dropIndex('shift_slots_order_idx');
            $table->boolean('is_supervisor')->default(false);
        });
        Schema::table('shift_role_slots', function (Blueprint $table): void {
            $table->index(['shift_id', 'is_supervisor', 'sort_order', 'id'], 'shift_slots_order_idx');
        });
        DB::statement('CREATE UNIQUE INDEX shift_slots_supervisor_unique ON shift_role_slots (shift_id) WHERE is_supervisor = true');
    }
};
