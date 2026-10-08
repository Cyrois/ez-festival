<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_engagement_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_meal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained()->nullOnDelete();
            // Stable origin metadata survives deletion; null supports direct person assignments.
            $table->unsignedBigInteger('source_shift_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('meal_name');
            $table->date('meal_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('shift_location_name')->nullable();
            $table->timestamp('shift_starts_at')->nullable();
            $table->timestamp('shift_ends_at')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            // A new token for each claim prevents stale Unclaim requests clearing a later claim.
            $table->uuid('claim_token')->nullable();
            $table->foreignId('warning_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('warning_overridden_at')->nullable();
            $table->unique(['team_engagement_id', 'source_shift_id', 'meal_id'], 'ma_source_unique');
            $table->index(['event_id', 'team_engagement_id', 'meal_date', 'meal_type_id'], 'ma_person_day_idx');
        });
        $grants = DB::table('shift_meal_people as grants')
            ->join('shift_meals', 'shift_meals.id', '=', 'grants.shift_meal_id')
            ->join('shift_assignments', 'shift_assignments.id', '=', 'grants.shift_assignment_id')
            ->join('shifts', 'shifts.id', '=', 'shift_meals.shift_id')
            ->join('locations', 'locations.id', '=', 'shifts.location_id')
            ->join('meals', 'meals.id', '=', 'shift_meals.meal_id')
            ->select('grants.*', 'shift_assignments.team_engagement_id', 'shifts.event_id', 'shifts.id as source_shift_id',
                'shift_meals.meal_id', 'meals.meal_type_id', 'meals.name as meal_name', 'meals.date as meal_date', 'meals.starts_at', 'meals.ends_at',
                'locations.name as shift_location_name', 'shifts.starts_at as shift_starts_at', 'shifts.ends_at as shift_ends_at')
            ->orderBy('grants.shift_meal_id')->orderBy('grants.shift_assignment_id');
        foreach ($grants->cursor() as $grant) {
            DB::table('meal_assignments')->insert([...(array) $grant, 'is_active' => true]);
        }
        Schema::drop('shift_meal_people');
    }

    public function down(): void
    {
        // The previous schema cannot represent direct assignments. Refuse a lossy rollback.
        if (DB::table('meal_assignments')->whereNull('source_shift_id')->exists()) {
            throw new RuntimeException('Direct meal assignments cannot be rolled back. Apply a forward migration instead.');
        }
        Schema::create('shift_meal_people', function (Blueprint $table): void {
            $table->foreignId('shift_meal_id');
            $table->foreignId('shift_assignment_id');
            $table->foreign('shift_meal_id', 'smp_meal_fk')->references('id')->on('shift_meals')->cascadeOnDelete();
            $table->foreign('shift_assignment_id', 'smp_assignment_fk')->references('id')->on('shift_assignments')->cascadeOnDelete();
            $table->unique(['shift_meal_id', 'shift_assignment_id'], 'smp_meal_assignment_unique');
            $table->index('shift_assignment_id', 'smp_assignment_idx');
        });
        foreach (DB::table('meal_assignments')->orderBy('id')->cursor() as $assignment) {
            if ($assignment->is_active && $assignment->shift_meal_id !== null && $assignment->shift_assignment_id !== null) {
                DB::table('shift_meal_people')->insert(['shift_meal_id' => $assignment->shift_meal_id, 'shift_assignment_id' => $assignment->shift_assignment_id]);
            }
        }
        Schema::drop('meal_assignments');
    }
};
