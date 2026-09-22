<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlement_adjustments', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('entitlement_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['entitlement_item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('entitlement_adjustments', function (Blueprint $table) {
            $table->dropIndex(['entitlement_item_id', 'location_id']);
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
