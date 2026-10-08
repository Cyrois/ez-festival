<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_engagement_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_meal_id')->nullable()->constrained()->nullOnDelete();
            // Deliberately not an FK: deleting/recreating a shift meal must not reset a claim.
            $table->unsignedBigInteger('source_shift_id');
            $table->string('meal_name');
            $table->date('meal_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('shift_location_name');
            $table->timestamp('shift_starts_at');
            $table->timestamp('shift_ends_at');
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at');
            $table->foreignId('warning_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('warning_overridden_at')->nullable();
            $table->unique(['team_engagement_id', 'source_shift_id', 'meal_id'], 'mc_source_unique');
            $table->index(['event_id', 'team_engagement_id', 'meal_date', 'meal_type_id'], 'mc_person_day_idx');
        });
    }

    public function down(): void
    {
        // Destructive: claim history is lost; roll back later dependent migrations first.
        Schema::dropIfExists('meal_claims');
    }
};
