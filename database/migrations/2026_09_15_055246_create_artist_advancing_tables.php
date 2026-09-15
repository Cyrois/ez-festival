<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->boolean('can_manage_artists')->default(false);
        });

        // Existing members had unrestricted organization access before permissions existed.
        DB::table('organization_user')->update(['can_manage_artists' => true]);

        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
        Schema::create('artist_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
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
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('neutral');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
        Schema::create('artist_label_assignments', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained()->cascadeOnDelete();
            $table->primary(['artist_id', 'artist_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_label_assignments');
        Schema::dropIfExists('artist_labels');
        Schema::dropIfExists('artist_engagements');
        Schema::dropIfExists('artists');
        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropColumn('can_manage_artists');
        });
    }
};
