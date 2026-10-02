<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'name']);
            $table->unique(['id', 'event_id']);
        });

        Schema::create('team_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('team_form_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['applied', 'reviewing', 'hired', 'declined'])->default('applied');
            $table->enum('employment_type', ['volunteer', 'paid'])->default('volunteer');
            $table->decimal('hourly_pay', 10, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
            $table->index(['event_id', 'status']);
            $table->index(['team_form_id', 'submitted_at']);
            $table->index(['group_id', 'event_id']);
            $table->foreign(['group_id', 'event_id'])
                ->references(['id', 'event_id'])
                ->on('groups')
                ->restrictOnDelete();
        });

        Schema::create('team_engagement_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['team_engagement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_engagement_notes');
        Schema::dropIfExists('team_engagements');
        Schema::dropIfExists('groups');
    }
};
