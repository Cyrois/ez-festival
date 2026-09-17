<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['Food & Beverage', 'Artisan'] as $name) {
            if (! DB::table('vendor_types')->where('name', $name)->exists()) {
                DB::table('vendor_types')->insert([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('vendor_types')
            ->whereIn('name', ['Food & Beverage', 'Artisan'])
            ->delete();
    }
};
