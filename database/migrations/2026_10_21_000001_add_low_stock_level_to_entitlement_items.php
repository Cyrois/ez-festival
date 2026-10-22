<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->integer('low_stock_level')->nullable());
    }

    public function down(): void
    {
        Schema::table('entitlement_items', fn (Blueprint $table) => $table->dropColumn('low_stock_level'));
    }
};
