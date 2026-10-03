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
            DB::statement('CREATE TABLE shift_breaks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shift_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                duration_minutes INTEGER NOT NULL CONSTRAINT shift_breaks_duration_check CHECK (duration_minutes IN (15, 30)),
                starts_at DATETIME NOT NULL,
                sort_order INTEGER NOT NULL CONSTRAINT shift_breaks_order_check CHECK (sort_order >= 0),
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT shift_breaks_shift_fk FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE CASCADE
            )');
        } else {
            Schema::create('shift_breaks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_id');
                $table->string('name');
                $table->integer('duration_minutes');
                $table->dateTime('starts_at');
                $table->integer('sort_order');
                $table->timestamps();
                $table->foreign('shift_id', 'shift_breaks_shift_fk')->references('id')->on('shifts')->cascadeOnDelete();
            });

            DB::statement('ALTER TABLE shift_breaks ADD CONSTRAINT shift_breaks_duration_check CHECK (duration_minutes IN (15, 30))');
            DB::statement('ALTER TABLE shift_breaks ADD CONSTRAINT shift_breaks_order_check CHECK (sort_order >= 0)');
        }

        Schema::table('shift_breaks', function (Blueprint $table): void {
            $table->index(['shift_id', 'sort_order', 'id'], 'shift_breaks_order_idx');
        });
    }

    public function down(): void
    {
        // Rolling back discards entered breaks; prefer a forward fix after use.
        Schema::dropIfExists('shift_breaks');
    }
};
