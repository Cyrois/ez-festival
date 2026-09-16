<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->timestamps();
        });

        Schema::create('artist_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('idea');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['artist_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('artist_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('color')->default('neutral');
            $table->timestamps();
        });

        // Legacy artist-scoped pivot retained for model relations / regression asserts;
        // engagement-scoped labels are canonical and are what the app writes.
        Schema::create('artist_label_assignments', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained()->cascadeOnDelete();
            $table->primary(['artist_id', 'artist_label_id']);
        });

        Schema::create('artist_engagement_label_assignments', function (Blueprint $table) {
            $table->foreignId('artist_engagement_id')->constrained('artist_engagements')->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained('artist_labels')->cascadeOnDelete();
            $table->primary(['artist_engagement_id', 'artist_label_id'], 'artist_engagement_label_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_engagement_label_assignments');
        Schema::dropIfExists('artist_label_assignments');
        Schema::dropIfExists('artist_labels');
        Schema::dropIfExists('artist_engagements');
        Schema::dropIfExists('artists');
    }
};
