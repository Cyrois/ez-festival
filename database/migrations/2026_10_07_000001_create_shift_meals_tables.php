<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_meals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id');
            $table->foreignId('meal_id');
            $table->timestamps();
            $table->foreign('shift_id', 'sm_shift_fk')->references('id')->on('shifts')->cascadeOnDelete();
            $table->foreign('meal_id', 'sm_meal_fk')->references('id')->on('meals')->restrictOnDelete();
            $table->unique(['shift_id', 'meal_id'], 'sm_shift_meal_unique');
            $table->index('meal_id', 'sm_meal_idx');
        });
        Schema::create('shift_meal_people', function (Blueprint $table): void {
            $table->foreignId('shift_meal_id');
            $table->foreignId('shift_assignment_id');
            $table->foreign('shift_meal_id', 'smp_meal_fk')->references('id')->on('shift_meals')->cascadeOnDelete();
            $table->foreign('shift_assignment_id', 'smp_assignment_fk')->references('id')->on('shift_assignments')->cascadeOnDelete();
            $table->unique(['shift_meal_id', 'shift_assignment_id'], 'smp_meal_assignment_unique');
            $table->index('shift_assignment_id', 'smp_assignment_idx');
        });
    }

    public function down(): void
    {
        // Destructive: roll back future claim migrations before removing meal grants.
        Schema::dropIfExists('shift_meal_people');
        Schema::dropIfExists('shift_meals');
    }
};
