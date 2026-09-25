<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('team_form_fields')
            ->where('key', 'email')
            ->update(['required' => true]);
    }

    public function down(): void
    {
        // Name and email are permanent required fields for Team forms.
    }
};
