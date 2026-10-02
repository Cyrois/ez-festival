<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the first supervisor in insertion order on existing shifts.
        DB::statement('UPDATE shift_role_slots SET is_supervisor = false WHERE id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY shift_id ORDER BY sort_order, id) AS supervisor_rank
                FROM shift_role_slots WHERE is_supervisor = true
            ) ranked WHERE supervisor_rank > 1
        )');
        DB::statement('CREATE UNIQUE INDEX shift_slots_supervisor_unique ON shift_role_slots (shift_id) WHERE is_supervisor = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX shift_slots_supervisor_unique');
        // Previous multiple-supervisor selections cannot be reconstructed.
    }
};
