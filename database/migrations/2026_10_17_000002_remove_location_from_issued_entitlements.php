<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A legacy location must already survive in the immutable ledger. Do
        // not invent historical stock movements to make this migration pass.
        $unrepresented = DB::table('issued_entitlements as ie')->whereNotNull('ie.location_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('stock_movements as sm')
                    ->join('stock_holders as sh', 'sh.id', '=', 'sm.from_holder_id')
                    ->whereColumn('sm.issued_entitlement_id', 'ie.id')
                    ->whereColumn('sm.entitlement_item_id', 'ie.entitlement_item_id')
                    ->whereColumn('sh.location_id', 'ie.location_id')
                    ->where('sm.type', 'give_out');
            })->exists();
        if ($unrepresented) {
            throw new RuntimeException('Issued location history is missing a linked give-out movement. Reconcile history before removing location_id.');
        }

        Schema::table('issued_entitlements', fn (Blueprint $table) => $table->dropIndex('issued_location_idx'));
        $this->alterIssuedTable(fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
    }

    public function down(): void
    {
        if (DB::table('issued_entitlements')->exists()) {
            throw new RuntimeException('Person-issued stock cannot be restored to a location. Restore a backup or apply a forward migration.');
        }

        $this->alterIssuedTable(fn (Blueprint $table) => $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('issued_entitlements', fn (Blueprint $table) => $table->index('location_id', 'issued_location_idx'));
    }

    private function alterIssuedTable(Closure $change): void
    {
        // SQLite rebuilds the table to remove a foreign key. Preserve the
        // ledger triggers that reference it across that temporary table swap.
        $triggers = DB::getDriverName() === 'sqlite'
            ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND sql LIKE '%issued_entitlements%'")
            : [];
        $deferred = null;
        if (DB::getDriverName() === 'sqlite') {
            $deferred = DB::selectOne('PRAGMA defer_foreign_keys')->defer_foreign_keys;
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }
        foreach ($triggers as $trigger) {
            DB::statement('DROP TRIGGER "'.str_replace('"', '""', $trigger->name).'"');
        }
        Schema::table('issued_entitlements', $change);
        foreach ($triggers as $trigger) {
            DB::unprepared($trigger->sql);
        }
        if ($deferred !== null) {
            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('Foreign key check failed after rebuilding issued entitlements.');
            }
            DB::statement('PRAGMA defer_foreign_keys = '.($deferred ? 'ON' : 'OFF'));
        }
    }
};
