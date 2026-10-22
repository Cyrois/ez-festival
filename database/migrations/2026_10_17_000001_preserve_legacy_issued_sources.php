<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This compatibility step sorts before the shipped location-removal
        // migration. Older installations can have issues predating the ledger.
        $hasLocations = Schema::hasColumn('issued_entitlements', 'location_id');
        if ($hasLocations) {
            $wrongEvent = DB::table('issued_entitlements as ie')
                ->join('entitlement_items as i', 'i.id', '=', 'ie.entitlement_item_id')
                ->join('locations as l', 'l.id', '=', 'ie.location_id')
                ->whereColumn('i.event_id', '<>', 'l.event_id')->exists();
            $conflictingMovement = DB::table('issued_entitlements as ie')
                ->join('stock_movements as sm', 'sm.issued_entitlement_id', '=', 'ie.id')
                ->join('stock_holders as sh', 'sh.id', '=', 'sm.from_holder_id')
                ->whereNotNull('ie.location_id')
                ->where(fn ($q) => $q->whereNull('sh.location_id')
                    ->orWhereColumn('sh.location_id', '<>', 'ie.location_id'))->exists();
            if ($wrongEvent || $conflictingMovement) {
                throw new RuntimeException('Legacy issued locations conflict with event or movement history. Reconcile the conflicting sources before migrating.');
            }
        }

        Schema::create('legacy_issued_entitlement_sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('issued_entitlement_id')->primary('lies_pk');
            $table->unsignedBigInteger('location_id');
            $table->foreign('issued_entitlement_id', 'lies_issue_fk')->references('id')->on('issued_entitlements')->restrictOnDelete();
            $table->foreign('location_id', 'lies_location_fk')->references('id')->on('locations')->restrictOnDelete();
            $table->index('location_id', 'lies_location_idx');
        });

        if (! $hasLocations) {
            return;
        }

        // Preserve known source metadata, not a new debit. A missing historical
        // give-out must never be manufactured against today's stock balance.
        DB::table('legacy_issued_entitlement_sources')->insertUsing(['issued_entitlement_id', 'location_id'],
            DB::table('issued_entitlements as ie')->whereNotNull('ie.location_id')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as sm')
                    ->whereColumn('sm.issued_entitlement_id', 'ie.id'))
                ->select('ie.id', 'ie.location_id'));
        DB::table('issued_entitlements')->whereIn('id', DB::table('legacy_issued_entitlement_sources')->select('issued_entitlement_id'))
            ->update(['location_id' => null]);
    }

    public function down(): void
    {
        $sources = DB::table('legacy_issued_entitlement_sources');
        if ($sources->exists()) {
            if (! Schema::hasColumn('issued_entitlements', 'location_id')) {
                throw new RuntimeException('Legacy issued source history must be preserved. Restore a backup or fix forward instead of dropping it.');
            }
            $conflicts = DB::table('issued_entitlements as ie')
                ->join('legacy_issued_entitlement_sources as legacy', 'legacy.issued_entitlement_id', '=', 'ie.id')
                ->whereNotNull('ie.location_id')->whereColumn('ie.location_id', '<>', 'legacy.location_id')->exists();
            if ($conflicts) {
                throw new RuntimeException('Legacy issued sources conflict with the restored location column. Reconcile history before rollback.');
            }
            DB::table('issued_entitlements')->whereIn('id', $sources->select('issued_entitlement_id'))
                ->update(['location_id' => DB::raw('(SELECT location_id FROM legacy_issued_entitlement_sources WHERE issued_entitlement_id = issued_entitlements.id)')]);
        }
        Schema::drop('legacy_issued_entitlement_sources');
    }
};
