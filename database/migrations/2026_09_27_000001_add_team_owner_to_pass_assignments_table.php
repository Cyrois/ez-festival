<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keep this filename stable: Laravel records migration basenames in the database.
return new class extends Migration
{
    public function up(): void
    {
        $this->dropOwnerConstraint();

        Schema::table('pass_assignments', function (Blueprint $table) {
            $table->foreignId('team_engagement_id')
                ->nullable()
                ->after('event_patron_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $this->createOwnerConstraint(includeTeam: true);
    }

    public function down(): void
    {
        if (DB::table('pass_assignments')->whereNotNull('team_engagement_id')->exists()) {
            throw new RuntimeException('Cannot roll back while Team-owned pass assignments exist.');
        }

        $this->dropOwnerConstraint();

        Schema::table('pass_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_engagement_id');
        });

        $this->createOwnerConstraint(includeTeam: false);
    }

    private function dropOwnerConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS pass_assignments_one_owner_insert');
            DB::statement('DROP TRIGGER IF EXISTS pass_assignments_one_owner_update');

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pass_assignments DROP CHECK pass_assignments_exactly_one_owner');

            return;
        }

        DB::statement('ALTER TABLE pass_assignments DROP CONSTRAINT pass_assignments_exactly_one_owner');
    }

    private function createOwnerConstraint(bool $includeTeam): void
    {
        $ownerColumns = [
            'artist_engagement_id',
            'vendor_engagement_id',
            'event_patron_id',
        ];

        if ($includeTeam) {
            $ownerColumns[] = 'team_engagement_id';
        }

        $exactlyOneOwner = '('.collect($ownerColumns)
            ->map(fn (string $column): string => "CASE WHEN {$column} IS NOT NULL THEN 1 ELSE 0 END")
            ->implode(' + ').') = 1';

        if (DB::getDriverName() === 'sqlite') {
            $sqliteExactlyOneOwner = collect($ownerColumns)->reduce(
                fn (string $constraint, string $column): string => str_replace($column, "NEW.{$column}", $constraint),
                $exactlyOneOwner,
            );
            $updatedColumns = implode(', ', $ownerColumns);

            DB::unprepared("CREATE TRIGGER pass_assignments_one_owner_insert
                BEFORE INSERT ON pass_assignments
                FOR EACH ROW WHEN NOT ({$sqliteExactlyOneOwner})
                BEGIN SELECT RAISE(ABORT, 'pass assignments require exactly one owner'); END;");
            DB::unprepared("CREATE TRIGGER pass_assignments_one_owner_update
                BEFORE UPDATE OF {$updatedColumns} ON pass_assignments
                FOR EACH ROW WHEN NOT ({$sqliteExactlyOneOwner})
                BEGIN SELECT RAISE(ABORT, 'pass assignments require exactly one owner'); END;");

            return;
        }

        DB::statement("ALTER TABLE pass_assignments
            ADD CONSTRAINT pass_assignments_exactly_one_owner CHECK ({$exactlyOneOwner})");
    }
};
