<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->timestamps();
        });

        Schema::create('vendor_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('idea');
            $table->timestamps();

            $table->unique(['vendor_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('vendor_engagement_people', function (Blueprint $table) {
            $table->foreignId('vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['vendor_engagement_id', 'person_id']);
            $table->index(['vendor_engagement_id', 'is_primary']);
        });

        Schema::create('vendor_engagement_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['vendor_engagement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_engagement_notes');
        Schema::dropIfExists('vendor_engagement_people');
        Schema::dropIfExists('vendor_engagements');
        Schema::dropIfExists('vendors');
    }
};
