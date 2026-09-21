<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_item_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('color')->default('primary');
            $table->timestamps();

            $table->unique(['event_id', 'name_key']);
        });

        Schema::create('entitlement_item_label_assignments', function (Blueprint $table) {
            $table->foreignId('entitlement_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entitlement_item_label_id')->constrained()->cascadeOnDelete();
            $table->primary(['entitlement_item_id', 'entitlement_item_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_item_label_assignments');
        Schema::dropIfExists('entitlement_item_labels');
    }
};
