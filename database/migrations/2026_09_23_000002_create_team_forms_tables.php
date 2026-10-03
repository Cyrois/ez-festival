<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->enum('status', ['draft', 'live'])->default('draft');
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('team_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['team_form_id', 'key']);
            $table->index(['team_form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_form_fields');
        Schema::dropIfExists('team_forms');
    }
};
