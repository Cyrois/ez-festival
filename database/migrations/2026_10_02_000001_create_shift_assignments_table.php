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
            DB::statement('CREATE TABLE shift_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shift_id INTEGER NOT NULL,
                team_engagement_id INTEGER NOT NULL,
                shift_role_slot_id INTEGER NULL,
                role_id INTEGER NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT shift_assignments_interval_check CHECK (starts_at < ends_at),
                CONSTRAINT shift_assignments_shift_fk FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE CASCADE,
                CONSTRAINT shift_assignments_engagement_fk FOREIGN KEY (team_engagement_id) REFERENCES team_engagements (id) ON DELETE RESTRICT,
                CONSTRAINT shift_assignments_slot_fk FOREIGN KEY (shift_role_slot_id) REFERENCES shift_role_slots (id) ON DELETE SET NULL,
                CONSTRAINT shift_assignments_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
            )');
        } else {
            Schema::create('shift_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_id');
                $table->foreignId('team_engagement_id');
                $table->foreignId('shift_role_slot_id')->nullable();
                $table->foreignId('role_id');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->timestamps();
                $table->foreign('shift_id', 'shift_assignments_shift_fk')->references('id')->on('shifts')->cascadeOnDelete();
                $table->foreign('team_engagement_id', 'shift_assignments_engagement_fk')->references('id')->on('team_engagements')->restrictOnDelete();
                $table->foreign('shift_role_slot_id', 'shift_assignments_slot_fk')->references('id')->on('shift_role_slots')->nullOnDelete();
                $table->foreign('role_id', 'shift_assignments_role_fk')->references('id')->on('roles')->restrictOnDelete();
            });
            DB::statement('ALTER TABLE shift_assignments ADD CONSTRAINT shift_assignments_interval_check CHECK (starts_at < ends_at)');
        }
        Schema::table('shift_assignments', function (Blueprint $table): void {
            $table->unique(['shift_id', 'team_engagement_id'], 'shift_assignments_member_unique');
            $table->index(['shift_role_slot_id', 'role_id'], 'shift_assignments_slot_idx');
            $table->index(['team_engagement_id', 'starts_at', 'ends_at'], 'shift_assignments_overlap_idx');
        });
    }

    public function down(): void
    {
        // Destructive: rolling back permanently discards assignment history.
        Schema::dropIfExists('shift_assignments');
    }
};
