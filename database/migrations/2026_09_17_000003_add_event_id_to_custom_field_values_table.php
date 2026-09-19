<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Folded into 2026_09_17_000002_create_custom_fields_tables (migrate:fresh style).
        // event_id uses cascadeOnDelete so IFNULL/COALESCE(event_id, 0) unique index
        // cannot collide when multiple events for the same owner are deleted.
    }

    public function down(): void
    {
        //
    }
};
