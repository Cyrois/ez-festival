<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['event_id', 'name']);
            $table->unique(['id', 'event_id']);
        });

        Schema::create('team_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->enum('status', ['applied', 'reviewing', 'hired', 'declined'])->default('applied');
            $table->enum('employment_type', ['volunteer', 'paid'])->default('volunteer');
            $table->decimal('hourly_pay', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
            $table->index(['event_id', 'status']);
            $table->index(['group_id', 'event_id']);
            $table->foreign(['group_id', 'event_id'])
                ->references(['id', 'event_id'])
                ->on('groups')
                ->restrictOnDelete();
        });

        if (DB::table('app_config')->where('key', 'crew')->exists()
            && ! DB::table('app_config')->where('key', 'team')->exists()) {
            DB::table('app_config')->where('key', 'crew')->update(['key' => 'team']);
        }
    }

    public function down(): void
    {
        if (DB::table('app_config')->where('key', 'team')->exists()
            && ! DB::table('app_config')->where('key', 'crew')->exists()) {
            DB::table('app_config')->where('key', 'team')->update(['key' => 'crew']);
        }

        Schema::dropIfExists('team_engagements');
        Schema::dropIfExists('groups');
    }
};
