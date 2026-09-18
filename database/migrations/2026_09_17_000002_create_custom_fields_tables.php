<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('target');
            $table->string('label');
            $table->string('key');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'target', 'key']);
            $table->index(['organization_id', 'target', 'active', 'sort_order']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->morphs('custom_fieldable');
            $table->text('value_text')->nullable();
            $table->string('value_search', 255)->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(
                ['custom_field_id', 'custom_fieldable_type', 'custom_fieldable_id'],
                'custom_field_value_owner_unique',
            );
            $table->index(['custom_field_id', 'value_search']);
            $table->index(['custom_field_id', 'value_number']);
            $table->index(['custom_field_id', 'value_date']);
            $table->index(['custom_field_id', 'value_boolean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
