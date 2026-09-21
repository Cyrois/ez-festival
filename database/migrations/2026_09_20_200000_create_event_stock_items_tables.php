<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['event_id', 'name']);
        });

        Schema::create('entitlement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_item_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitlement_item_id', 'created_at']);
        });

        Schema::create('pass_type_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entitlement_item_id')->constrained()->restrictOnDelete();
            $table->integer('sort_order');
            $table->timestamps();
        });

        Schema::create('expected_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entitlement_item_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('expected');
            $table->timestamps();
        });

        Schema::create('issued_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expected_entitlement_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('entitlement_item_id')->constrained()->restrictOnDelete();
            $table->string('code')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_entitlements');
        Schema::dropIfExists('expected_entitlements');
        Schema::dropIfExists('pass_type_entitlements');
        Schema::dropIfExists('entitlement_adjustments');
        Schema::dropIfExists('entitlement_items');
    }
};
