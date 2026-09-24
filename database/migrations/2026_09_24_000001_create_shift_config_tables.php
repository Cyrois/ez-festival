<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unique(['id', 'event_id']);
        });

        Schema::create('shift_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['event_id', 'name']);
            $table->unique(['id', 'event_id']);
        });

        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->string('name');
            $table->timestamps();

            $table->index(['event_id', 'location_id']);
            $table->unique(['id', 'event_id']);
            $table->foreign(['location_id', 'event_id'])
                ->references(['id', 'event_id'])
                ->on('locations')
                ->restrictOnDelete();
        });

        Schema::create('shift_template_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_template_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('shift_role_id');
            $table->unsignedInteger('headcount');
            $table->timestamps();

            $table->unique(['shift_template_id', 'shift_role_id']);
            $table->foreign(['shift_template_id', 'event_id'])
                ->references(['id', 'event_id'])
                ->on('shift_templates')
                ->cascadeOnDelete();
            $table->foreign(['shift_role_id', 'event_id'])
                ->references(['id', 'event_id'])
                ->on('shift_roles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_template_roles');
        Schema::dropIfExists('shift_templates');
        Schema::dropIfExists('shift_roles');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['id', 'event_id']);
        });
    }
};
