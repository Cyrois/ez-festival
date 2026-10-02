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

        Schema::create('artist_engagement_label_assignments', function (Blueprint $table) {
            $table->foreignId('artist_engagement_id')->constrained('artist_engagements', indexName: 'artist_label_assignment_engagement_fk')->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained('artist_labels')->cascadeOnDelete();
            $table->primary(['artist_engagement_id', 'artist_label_id'], 'artist_engagement_label_primary');
        });

        Schema::create('artist_engagement_people', function (Blueprint $table) {
            $table->foreignId('artist_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['artist_engagement_id', 'person_id']);
            $table->index(['artist_engagement_id', 'is_primary']);
        });

        Schema::create('artist_engagement_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['artist_engagement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_engagement_notes');
        Schema::dropIfExists('artist_engagement_people');
        Schema::dropIfExists('artist_engagement_label_assignments');
        Schema::dropIfExists('artist_labels');
        Schema::dropIfExists('artist_engagements');
        Schema::dropIfExists('artists');
    }
};
