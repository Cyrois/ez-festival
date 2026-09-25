<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('people')->whereNull('email')->exists()) {
            throw new RuntimeException('Every person must have an email before making people.email required.');
        }

        Schema::table('people', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Baseline people.email is required; do not weaken it on rollback.
    }
};
