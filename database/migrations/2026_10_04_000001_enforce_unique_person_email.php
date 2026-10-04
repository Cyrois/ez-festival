<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = "nullif(lower(trim(email)), '')";
        $duplicates = DB::table('people')->selectRaw("{$key} as email_key, count(*) as aggregate")
            ->whereRaw("{$key} is not null")->groupByRaw($key)->havingRaw('count(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Resolve duplicate non-empty Person emails before applying people_email_identity_unique. No people were merged or changed.');
        }
        DB::statement("CREATE UNIQUE INDEX people_email_identity_unique ON people ({$key})");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX people_email_identity_unique');
    }
};
