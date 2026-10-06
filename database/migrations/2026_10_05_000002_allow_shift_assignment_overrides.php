<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->changeRoleNullability(nullable: true);
    }

    public function down(): void
    {
        if (DB::table('shift_assignments')->whereNull('role_id')->exists()) {
            throw new RuntimeException('Cannot roll back while role-free shift overrides exist. Use a forward migration instead.');
        }

        $this->changeRoleNullability(nullable: false);
    }

    private function changeRoleNullability(bool $nullable): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE shift_assignments ALTER COLUMN role_id '.($nullable ? 'DROP' : 'SET').' NOT NULL');

            return;
        }

        // SQLite rebuilds must preserve the original CHECKs, indexes, sequence and
        // child break rows. Foreign keys must be disabled outside the transaction.
        $foreignKeys = (bool) DB::scalar('PRAGMA foreign_keys');
        Schema::disableForeignKeyConstraints();
        try {
            if (DB::scalar('PRAGMA foreign_keys')) {
                throw new RuntimeException('The SQLite assignment rebuild must run outside a transaction.');
            }
            DB::transaction(function () use ($nullable): void {
                $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'shift_assignments')->value('sql');
                $sql = preg_replace('/^CREATE TABLE\s+(?:"shift_assignments"|shift_assignments)\s*\(/i', 'CREATE TABLE shift_assignments_updated (', $sql, 1, $renamed);
                $sql = preg_replace('/\brole_id\s+INTEGER\s+(?:NOT NULL|NULL)\b/i', 'role_id INTEGER '.($nullable ? 'NULL' : 'NOT NULL'), $sql, 1, $changed);
                if ($renamed !== 1 || $changed !== 1) {
                    throw new RuntimeException('Unexpected SQLite assignment schema; no data has been changed.');
                }
                $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'shift_assignments')->whereNotNull('sql')->pluck('sql');
                $sequence = (int) DB::table('sqlite_sequence')->where('name', 'shift_assignments')->value('seq');
                DB::statement($sql);
                DB::statement('INSERT INTO shift_assignments_updated SELECT * FROM shift_assignments');
                Schema::drop('shift_assignments');
                Schema::rename('shift_assignments_updated', 'shift_assignments');
                foreach ($indexes as $index) {
                    DB::statement($index);
                }
                DB::table('sqlite_sequence')->updateOrInsert(['name' => 'shift_assignments'], ['seq' => max($sequence, (int) DB::table('shift_assignments')->max('id'))]);
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Foreign key check failed after rebuilding shift assignments.');
                }
            });
        } finally {
            if ($foreignKeys) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }
};
