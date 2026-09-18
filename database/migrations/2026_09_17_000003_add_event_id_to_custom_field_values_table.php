<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('custom_field_id');
            $table->index(['event_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'custom_field_id']);
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
