<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No backfill: applicants may be ungrouped; hired members must already have a group.
        if (DB::table('team_engagements')->where('status', 'hired')->whereNull('group_id')->exists()) {
            throw new RuntimeException('Assign groups to hired Team members before running this migration.');
        }

        if (DB::getDriverName() === 'sqlite') {
            // Equivalent guards avoid rebuilding the table and its dependent foreign keys.
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $name = 'team_hired_group_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON team_engagements
                    WHEN NEW.status = 'hired' AND NEW.group_id IS NULL
                    BEGIN SELECT RAISE(ABORT, 'team_engagements_hired_group_check'); END");
            }

            return;
        }

        DB::statement("ALTER TABLE team_engagements ADD CONSTRAINT team_engagements_hired_group_check CHECK (status <> 'hired' OR group_id IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS team_hired_group_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS team_hired_group_update');

            return;
        }

        DB::statement('ALTER TABLE team_engagements DROP CONSTRAINT team_engagements_hired_group_check');
    }
};
