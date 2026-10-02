<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot add CHECK constraints after CREATE TABLE.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE shift_role_slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shift_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                needed INTEGER NOT NULL CONSTRAINT shift_slots_needed_check CHECK (needed > 0 AND needed <= 2147483647),
                sort_order INTEGER NOT NULL CONSTRAINT shift_slots_order_check CHECK (sort_order >= 0),
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT shift_slots_shift_fk FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE CASCADE,
                CONSTRAINT shift_slots_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
            )');
        } else {
            Schema::create('shift_role_slots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_id');
                $table->foreignId('role_id');
                $table->integer('needed');
                $table->integer('sort_order');
                $table->timestamps();
                $table->foreign('shift_id', 'shift_slots_shift_fk')->references('id')->on('shifts')->cascadeOnDelete();
                $table->foreign('role_id', 'shift_slots_role_fk')->references('id')->on('roles')->restrictOnDelete();
            });

            DB::statement('ALTER TABLE shift_role_slots ADD CONSTRAINT shift_slots_needed_check CHECK (needed > 0)');
            DB::statement('ALTER TABLE shift_role_slots ADD CONSTRAINT shift_slots_order_check CHECK (sort_order >= 0)');
        }

        Schema::table('shift_role_slots', function (Blueprint $table): void {
            $table->index(['shift_id', 'sort_order', 'id'], 'shift_slots_order_idx');
        });
    }

    public function down(): void
    {
        // Rolling back discards entered role needs; prefer a forward fix after use.
        Schema::dropIfExists('shift_role_slots');
    }
};
