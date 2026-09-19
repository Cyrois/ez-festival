<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained()->nullOnDelete()->unique();
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $personId = DB::table('people')->insertGetId([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['person_id' => $personId]);
        });

        Schema::create('artist_engagement_people', function (Blueprint $table) {
            $table->foreignId('artist_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['artist_engagement_id', 'person_id']);
            $table->index(['artist_engagement_id', 'is_primary']);
        });

        Schema::create('vendor_engagement_people', function (Blueprint $table) {
            $table->foreignId('vendor_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['vendor_engagement_id', 'person_id']);
            $table->index(['vendor_engagement_id', 'is_primary']);
        });

        Schema::create('pass_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('assignable');
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['pass_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_assignments');
        Schema::dropIfExists('vendor_engagement_people');
        Schema::dropIfExists('artist_engagement_people');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('people');
    }
};
