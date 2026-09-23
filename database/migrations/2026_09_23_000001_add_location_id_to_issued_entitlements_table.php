<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_entitlements', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('entitlement_item_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issued_entitlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
