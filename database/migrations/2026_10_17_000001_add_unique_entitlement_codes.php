<?php

use App\Support\EntitlementCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlement_items', function (Blueprint $table): void {
            $table->boolean('needs_unique_code')->default(false);
        });

        // One reservation per used value, including duplicate legacy codes. Keep
        // reservations until event deletion, independently of the current flag.
        Schema::create('entitlement_code_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id');
            // Lowercasing can expand a 255-character display code.
            $table->string('code_key', 510);
            $table->primary(['event_id', 'code_key'], 'ecr_event_code_pk');
            $table->foreign('event_id', 'ecr_event_fk')->references('id')->on('events')->restrictOnDelete();
        });

        DB::table('issued_entitlements as issued')
            ->join('entitlement_items as item', 'item.id', '=', 'issued.entitlement_item_id')
            ->whereNotNull('issued.code')
            ->select('issued.id', 'issued.code', 'item.event_id')
            ->chunkById(500, function ($rows): void {
                $reservations = [];
                foreach ($rows as $row) {
                    if (trim($row->code) !== '') {
                        $reservations[] = [
                            'event_id' => (int) $row->event_id,
                            'code_key' => EntitlementCode::key($row->code),
                        ];
                    }
                }
                if ($reservations !== []) {
                    DB::table('entitlement_code_reservations')->insertOrIgnore($reservations);
                }
            }, 'issued.id', 'id');
    }

    public function down(): void
    {
        // Display codes and all issuance/history rows remain intact.
        Schema::dropIfExists('entitlement_code_reservations');
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->dropColumn('needs_unique_code'));
    }
};
