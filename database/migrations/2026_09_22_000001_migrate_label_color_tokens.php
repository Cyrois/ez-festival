<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['artist_labels', 'pass_type_labels', 'entitlement_item_labels'] as $table) {
            DB::table($table)->where('color', 'primary')->update(['color' => 'teal']);
            DB::table($table)->where('color', 'secondary')->update(['color' => 'soft_blue']);
            DB::table($table)->where('color', 'neutral')->update(['color' => 'slate']);
        }
    }

    public function down(): void
    {
        foreach (['artist_labels', 'pass_type_labels', 'entitlement_item_labels'] as $table) {
            DB::table($table)->where('color', 'teal')->update(['color' => 'primary']);
            DB::table($table)->where('color', 'soft_blue')->update(['color' => 'secondary']);
            DB::table($table)->where('color', 'slate')->update(['color' => 'neutral']);
        }
    }
};
