<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone');
            $table->boolean('locked')->default(false);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('artist_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('application_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });

        DB::table('application_state')->insert([
            'id' => 1,
            'default_event_id' => null,
            'setup_completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_state');
        Schema::dropIfExists('artist_types');
        Schema::dropIfExists('vendor_types');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('events');
    }
};
