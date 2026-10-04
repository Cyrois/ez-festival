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
            $this->rebuildSqlite(withNames: false);

            return;
        }

        DB::statement('ALTER TABLE shift_breaks DROP CONSTRAINT shift_breaks_duration_check');
        DB::statement('ALTER TABLE shift_breaks ADD CONSTRAINT shift_breaks_duration_check CHECK (duration_minutes IN (15, 30, 45, 60))');
        Schema::table('shift_breaks', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        if (DB::table('shift_breaks')->whereNotIn('duration_minutes', [15, 30])->exists()) {
            throw new RuntimeException('Cannot roll back while 45- or 60-minute breaks exist. Use a forward migration instead.');
        }

        // Retired names cannot be recovered. Restore their schema with empty values,
        // preserving all break identities, lengths, timestamps, and ordering.
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(withNames: true);

            return;
        }

        DB::statement("ALTER TABLE shift_breaks ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement('ALTER TABLE shift_breaks ALTER COLUMN name DROP DEFAULT');
        DB::statement('ALTER TABLE shift_breaks DROP CONSTRAINT shift_breaks_duration_check');
        DB::statement('ALTER TABLE shift_breaks ADD CONSTRAINT shift_breaks_duration_check CHECK (duration_minutes IN (15, 30))');
    }

    private function rebuildSqlite(bool $withNames): void
    {
        // SQLite cannot replace a CHECK constraint. Keep the rebuild atomic and retain sequences.
        DB::transaction(function () use ($withNames): void {
            $sequence = (int) DB::table('sqlite_sequence')->where('name', 'shift_breaks')->value('seq');
            $nameColumn = $withNames ? 'name VARCHAR(255) NOT NULL,' : '';
            $durations = $withNames ? '15, 30' : '15, 30, 45, 60';
            DB::statement("CREATE TABLE shift_breaks_updated (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shift_id INTEGER NOT NULL,
                $nameColumn
                duration_minutes INTEGER NOT NULL CONSTRAINT shift_breaks_duration_check CHECK (duration_minutes IN ($durations)),
                starts_at DATETIME NOT NULL,
                sort_order INTEGER NOT NULL CONSTRAINT shift_breaks_order_check CHECK (sort_order >= 0),
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT shift_breaks_shift_fk FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE CASCADE
            )");
            $nameTarget = $withNames ? 'name,' : '';
            $nameValue = $withNames ? "''," : '';
            DB::statement("INSERT INTO shift_breaks_updated (id, shift_id, $nameTarget duration_minutes, starts_at, sort_order, created_at, updated_at)
                SELECT id, shift_id, $nameValue duration_minutes, starts_at, sort_order, created_at, updated_at FROM shift_breaks");
            Schema::drop('shift_breaks');
            Schema::rename('shift_breaks_updated', 'shift_breaks');
            DB::table('sqlite_sequence')->updateOrInsert(['name' => 'shift_breaks'], ['seq' => max($sequence, (int) DB::table('shift_breaks')->max('id'))]);
            Schema::table('shift_breaks', function (Blueprint $table): void {
                $table->index(['shift_id', 'sort_order', 'id'], 'shift_breaks_order_idx');
            });
        });
    }
};
