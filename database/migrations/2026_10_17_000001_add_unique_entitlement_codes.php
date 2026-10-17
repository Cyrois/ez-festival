<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlement_items', function (Blueprint $table): void {
            $table->boolean('needs_unique_code')->default(false);
        });

        // Reservations start with new check-ins and remain until event deletion,
        // independently of the current flag.
        Schema::create('entitlement_code_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id');
            // Lowercasing can expand a 255-character display code.
            $table->string('code_key', 510);
            $table->primary(['event_id', 'code_key'], 'ecr_event_code_pk');
            $table->foreign('event_id', 'ecr_event_fk')->references('id')->on('events')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Display codes and all issuance/history rows remain intact.
        Schema::dropIfExists('entitlement_code_reservations');
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->dropColumn('needs_unique_code'));
    }
};
