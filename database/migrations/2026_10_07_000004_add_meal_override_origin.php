<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_assignments', function (Blueprint $table): void {
            $table->boolean('is_override')->default(false);
            $table->unsignedBigInteger('override_given_by')->nullable();
            $table->foreign('override_given_by', 'ma_override_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('override_given_at')->nullable();
        });
    }

    public function down(): void
    {
        // Removing the discriminator would silently turn overrides into ordinary grants.
        if (DB::table('meal_assignments')->where('is_override', true)->exists()) {
            throw new RuntimeException('Meal overrides cannot be rolled back while they exist. Apply a forward migration instead.');
        }
        Schema::table('meal_assignments', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['override_given_by'] : 'ma_override_actor_fk');
            $table->dropColumn(['is_override', 'override_given_by', 'override_given_at']);
        });
    }
};
