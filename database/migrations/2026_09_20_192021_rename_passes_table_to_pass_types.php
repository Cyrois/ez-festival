<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // pass_types is created under its final name in the original schema migration.
        // This migration is retained for already-ordered development databases.
    }

    public function down(): void {}
};
