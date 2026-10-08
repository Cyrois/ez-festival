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
            DB::statement('CREATE TABLE meal_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                name VARCHAR(50) NOT NULL,
                name_key VARCHAR(255) NOT NULL,
                starts_at TIME NOT NULL,
                ends_at TIME NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT meal_types_window_check CHECK (starts_at <> ends_at),
                CONSTRAINT meal_types_event_fk FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
            )');
        } else {
            Schema::create('meal_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id');
                $table->string('name', 50);
                // Unicode lowercasing can expand a valid 50-character display name.
                $table->string('name_key');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('event_id', 'meal_types_event_fk')->references('id')->on('events')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE meal_types ADD CONSTRAINT meal_types_window_check CHECK (starts_at <> ends_at AND starts_at < TIME '24:00' AND ends_at < TIME '24:00')");
        }

        Schema::table('meal_types', function (Blueprint $table): void {
            $table->unique(['event_id', 'name_key'], 'meal_types_event_name_unique');
            $table->index(['event_id', 'sort_order', 'id'], 'meal_types_event_order_idx');
        });

    }

    public function down(): void
    {
        // Destructive: remove dependent Meals migrations first; all type changes are lost.
        Schema::dropIfExists('meal_types');
    }
};
