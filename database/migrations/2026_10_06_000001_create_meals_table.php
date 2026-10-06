<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE meals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                meal_type_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                name_key TEXT NOT NULL,
                date DATE NOT NULL,
                starts_at TIME NOT NULL,
                ends_at TIME NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT meals_window_check CHECK (
                    starts_at <> ends_at
                    AND time(starts_at) IS NOT NULL AND time(ends_at) IS NOT NULL
                    AND starts_at >= \'00:00:00\' AND starts_at < \'24:00:00\'
                    AND ends_at >= \'00:00:00\' AND ends_at < \'24:00:00\'
                ),
                CONSTRAINT meals_event_fk FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
                CONSTRAINT meals_type_fk FOREIGN KEY (meal_type_id) REFERENCES meal_types (id) ON DELETE RESTRICT
            )');
        } else {
            Schema::create('meals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id');
                $table->foreignId('meal_type_id');
                $table->string('name');
                // Unicode lowercasing can expand a valid display name.
                $table->text('name_key');
                $table->date('date');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->timestamps();
                $table->foreign('event_id', 'meals_event_fk')->references('id')->on('events')->cascadeOnDelete();
                $table->foreign('meal_type_id', 'meals_type_fk')->references('id')->on('meal_types')->restrictOnDelete();
            });
            DB::statement("ALTER TABLE meals ADD CONSTRAINT meals_window_check CHECK (starts_at <> ends_at AND starts_at < TIME '24:00' AND ends_at < TIME '24:00')");
        }

        Schema::table('meals', function (Blueprint $table): void {
            $table->unique(['event_id', 'name_key'], 'meals_event_name_unique');
            $table->index(['event_id', 'date', 'starts_at', 'id'], 'meals_event_date_idx');
        });
    }

    public function down(): void
    {
        // Destructive: roll back dependent shift/claim migrations first; meals are lost.
        Schema::dropIfExists('meals');
    }
};
