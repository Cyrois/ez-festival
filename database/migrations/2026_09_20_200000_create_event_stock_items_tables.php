<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'name_key']);
            $table->index(['event_id', 'name']);
        });

        Schema::create('event_stock_item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->foreignId('issued_to_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->nullableMorphs('contextable');
            $table->string('credential_code')->nullable();
            $table->timestamps();

            $table->index(['event_stock_item_id', 'created_at']);
            $table->index('issued_to_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_stock_item_movements');
        Schema::dropIfExists('event_stock_items');
    }
};
