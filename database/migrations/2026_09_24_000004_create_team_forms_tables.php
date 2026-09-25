<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->enum('status', ['draft', 'live'])->default('draft');
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('team_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['team_form_id', 'key']);
            $table->index(['team_form_id', 'sort_order']);
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite can add these columns in place. Using the schema rebuild path
            // here would discard the existing enum CHECK constraints.
            DB::statement('ALTER TABLE team_engagements ADD COLUMN team_form_id INTEGER NULL REFERENCES team_forms(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE team_engagements ADD COLUMN submitted_at DATETIME NULL');
            DB::statement('CREATE INDEX team_engagements_team_form_id_submitted_at_index ON team_engagements (team_form_id, submitted_at)');
        } else {
            Schema::table('team_engagements', function (Blueprint $table) {
                $table->foreignId('team_form_id')->nullable()->after('group_id')->constrained()->nullOnDelete();
                $table->timestamp('submitted_at')->nullable()->after('hourly_pay');
                $table->index(['team_form_id', 'submitted_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS team_engagements_team_form_id_submitted_at_index');
            DB::statement('ALTER TABLE team_engagements DROP COLUMN submitted_at');
            DB::statement('ALTER TABLE team_engagements DROP COLUMN team_form_id');
        } else {
            Schema::table('team_engagements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_form_id');
                $table->dropColumn('submitted_at');
            });
        }

        Schema::dropIfExists('team_form_fields');
        Schema::dropIfExists('team_forms');

    }
};
