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
            $table->string('email')->index();
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

        Schema::create('event_patrons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('do_not_contact')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
        });

        Schema::create('pass_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_engagement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_engagement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_patron_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['pass_type_id', 'created_at']);
        });

        $exactlyOneOwner = '(CASE WHEN artist_engagement_id IS NOT NULL THEN 1 ELSE 0 END'
            .' + CASE WHEN vendor_engagement_id IS NOT NULL THEN 1 ELSE 0 END'
            .' + CASE WHEN event_patron_id IS NOT NULL THEN 1 ELSE 0 END) = 1';

        if (DB::getDriverName() === 'sqlite') {
            $sqliteExactlyOneOwner = str_replace(
                ['artist_engagement_id', 'vendor_engagement_id', 'event_patron_id'],
                ['NEW.artist_engagement_id', 'NEW.vendor_engagement_id', 'NEW.event_patron_id'],
                $exactlyOneOwner,
            );
            DB::unprepared("CREATE TRIGGER pass_assignments_one_owner_insert
                BEFORE INSERT ON pass_assignments
                FOR EACH ROW WHEN NOT ({$sqliteExactlyOneOwner})
                BEGIN SELECT RAISE(ABORT, 'pass assignments require exactly one owner'); END;");
            DB::unprepared("CREATE TRIGGER pass_assignments_one_owner_update
                BEFORE UPDATE OF artist_engagement_id, vendor_engagement_id, event_patron_id ON pass_assignments
                FOR EACH ROW WHEN NOT ({$sqliteExactlyOneOwner})
                BEGIN SELECT RAISE(ABORT, 'pass assignments require exactly one owner'); END;");
        } else {
            DB::statement("ALTER TABLE pass_assignments
                ADD CONSTRAINT pass_assignments_exactly_one_owner CHECK ({$exactlyOneOwner})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS pass_assignments_one_owner_insert');
            DB::statement('DROP TRIGGER IF EXISTS pass_assignments_one_owner_update');
        }

        Schema::dropIfExists('pass_assignments');
        Schema::dropIfExists('vendor_engagement_people');
        Schema::dropIfExists('artist_engagement_people');
        Schema::dropIfExists('event_patrons');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('people');
    }
};
