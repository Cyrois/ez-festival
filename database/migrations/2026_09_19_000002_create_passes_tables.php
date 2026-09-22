<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->unsignedInteger('max_assignments')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'name_key']);
        });

        Schema::create('pass_type_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('color')->default('neutral');
            $table->timestamps();
        });

        Schema::create('pass_type_label_assignments', function (Blueprint $table) {
            $table->foreignId('pass_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_type_label_id')->constrained()->cascadeOnDelete();
            $table->primary(
                ['pass_type_id', 'pass_type_label_id'],
                'pass_type_label_assignment_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_type_label_assignments');
        Schema::dropIfExists('pass_type_labels');
        Schema::dropIfExists('pass_types');
    }
};
