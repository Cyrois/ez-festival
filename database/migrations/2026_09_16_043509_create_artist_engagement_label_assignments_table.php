<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_engagement_label_assignments', function (Blueprint $table) {
            $table->foreignId('artist_engagement_id')->constrained('artist_engagements')->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained('artist_labels')->cascadeOnDelete();
            $table->primary(['artist_engagement_id', 'artist_label_id'], 'artist_engagement_label_primary');
        });

        // Copy artist labels onto every engagement so existing list filters still show them.
        $assignments = DB::table('artist_label_assignments')->get();
        foreach ($assignments as $assignment) {
            $engagementIds = DB::table('artist_engagements')
                ->where('artist_id', $assignment->artist_id)
                ->pluck('id');

            foreach ($engagementIds as $engagementId) {
                DB::table('artist_engagement_label_assignments')->insertOrIgnore([
                    'artist_engagement_id' => $engagementId,
                    'artist_label_id' => $assignment->artist_label_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_engagement_label_assignments');
    }
};
