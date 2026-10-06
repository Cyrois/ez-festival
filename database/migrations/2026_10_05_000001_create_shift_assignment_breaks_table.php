<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE shift_assignment_breaks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shift_assignment_id INTEGER NOT NULL,
                shift_break_id INTEGER NULL,
                duration_minutes INTEGER NOT NULL CONSTRAINT sab_duration_check CHECK (duration_minutes IN (15, 30, 45, 60)),
                starts_at DATETIME NOT NULL,
                sort_order INTEGER NOT NULL CONSTRAINT sab_order_check CHECK (sort_order >= 0),
                created_at DATETIME NULL, updated_at DATETIME NULL,
                CONSTRAINT sab_assignment_fk FOREIGN KEY (shift_assignment_id) REFERENCES shift_assignments(id) ON DELETE CASCADE,
                CONSTRAINT sab_source_fk FOREIGN KEY (shift_break_id) REFERENCES shift_breaks(id) ON DELETE SET NULL
            )');
        } else {
            Schema::create('shift_assignment_breaks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_assignment_id')->constrained(indexName: 'sab_assignment_fk')->cascadeOnDelete();
                // Permanent nullable provenance: manual and orphaned personal breaks are unlinked.
                $table->foreignId('shift_break_id')->nullable()->constrained(indexName: 'sab_source_fk')->nullOnDelete();
                $table->unsignedSmallInteger('duration_minutes');
                $table->dateTime('starts_at');
                $table->unsignedInteger('sort_order');
                $table->timestamps();
            });
            DB::statement('ALTER TABLE shift_assignment_breaks ADD CONSTRAINT sab_duration_check CHECK (duration_minutes IN (15, 30, 45, 60))');
            DB::statement('ALTER TABLE shift_assignment_breaks ADD CONSTRAINT sab_order_check CHECK (sort_order >= 0)');
        }
        Schema::table('shift_assignment_breaks', function (Blueprint $table): void {
            $table->index(['shift_assignment_id', 'sort_order', 'id'], 'sab_order_idx');
            $table->index('shift_break_id', 'sab_source_idx');
        });

        // Bounded, model-independent backfill includes locked events. Pause scheduling writes during deployment.
        DB::table('shift_assignments')->orderBy('id')->chunkById(500, function ($assignments): void {
            $defaults = DB::table('shift_breaks')->whereIn('shift_id', $assignments->pluck('shift_id')->unique())->orderBy('sort_order')->orderBy('id')->get()->groupBy('shift_id');
            $rows = [];
            foreach ($assignments as $assignment) {
                foreach ($defaults->get($assignment->shift_id, collect()) as $break) {
                    $start = CarbonImmutable::parse($break->starts_at);
                    if ($start->lt(CarbonImmutable::parse($assignment->starts_at)) || $start->addMinutes($break->duration_minutes)->gt(CarbonImmutable::parse($assignment->ends_at))) {
                        continue;
                    }
                    $rows[] = ['shift_assignment_id' => $assignment->id, 'shift_break_id' => $break->id,
                        'duration_minutes' => $break->duration_minutes, 'starts_at' => $break->starts_at,
                        'sort_order' => $break->sort_order, 'created_at' => now(), 'updated_at' => now()];
                }
            }
            foreach (array_chunk($rows, 500) as $batch) {
                DB::table('shift_assignment_breaks')->insert($batch);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('shift_assignment_breaks')->exists()) {
            throw new RuntimeException('Personal break snapshots cannot be recovered after rollback. Use a forward migration.');
        }
        Schema::dropIfExists('shift_assignment_breaks');
    }
};
