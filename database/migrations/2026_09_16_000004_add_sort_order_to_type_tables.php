<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artist_types', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });

        Schema::table('vendor_types', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });

        foreach (['artist_types', 'vendor_types'] as $table) {
            DB::table($table)
                ->orderBy('id')
                ->get(['id'])
                ->each(function (object $type, int $position) use ($table): void {
                    DB::table($table)
                        ->where('id', $type->id)
                        ->update(['sort_order' => $position]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('artist_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('vendor_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
