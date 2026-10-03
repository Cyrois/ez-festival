<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_patrons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('do_not_contact')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
        });

        Schema::create('pass_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_engagement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_engagement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_patron_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_engagement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['pass_type_id', 'created_at']);
        });

        $ownerColumns = [
            'artist_engagement_id',
            'vendor_engagement_id',
            'event_patron_id',
            'team_engagement_id',
        ];

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

    public function down(): void
    {
        Schema::dropIfExists('pass_assignments');
        Schema::dropIfExists('event_patrons');
    }
};
