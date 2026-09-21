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
            $table->unsignedInteger('max_assignments')->nullable();
            $table->timestamps();
        });

        Schema::create('pass_type_label_assignments', function (Blueprint $table) {
            $table->foreignId('pass_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_label_id')->constrained()->cascadeOnDelete();
            $table->primary(
                ['pass_type_id', 'artist_label_id'],
                'pass_type_label_assignment_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_type_label_assignments');
        Schema::dropIfExists('pass_types');
    }
};
