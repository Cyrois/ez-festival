<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        $organization = $this->assertDatabaseCanBecomeSingleOrganization();

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_event_id')
                ->nullable()
                ->after('password')
                ->constrained('events')
                ->nullOnDelete();
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
            'default_event_id' => $organization?->active_event_id,
            'setup_completed_at' => $organization?->setup_completed_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($organization !== null) {
            DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->whereNotNull('current_event_id')
                ->orderBy('id')
                ->each(function (object $membership): void {
                    DB::table('users')
                        ->where('id', $membership->user_id)
                        ->update(['current_event_id' => $membership->current_event_id]);
                });
        }

        Schema::table('organization_artists', function (Blueprint $table) {
            $table->string('name_key')->nullable()->after('name');
        });
        Schema::table('artist_labels', function (Blueprint $table) {
            $table->string('name_key')->nullable()->after('name');
        });

        DB::table('organization_artists')->orderBy('id')->each(function (object $artist): void {
            DB::table('organization_artists')
                ->where('id', $artist->id)
                ->update(['name_key' => $this->normalizeName($artist->name)]);
        });
        DB::table('artist_labels')->orderBy('id')->each(function (object $label): void {
            DB::table('artist_labels')
                ->where('id', $label->id)
                ->update(['name_key' => $this->normalizeName($label->name)]);
        });

        Schema::drop('organization_user');

        Schema::table('organization_artists', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::rename('organization_artists', 'artists');
        Schema::table('artists', function (Blueprint $table) {
            $table->string('name_key')->nullable(false)->change();
            $table->unique('name_key');
        });

        Schema::table('artist_labels', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->dropConstrainedForeignId('organization_id');
            $table->string('name_key')->nullable(false)->change();
            $table->unique('name_key');
        });

        Schema::table('artist_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::table('vendor_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['active_event_id']);
        });
        Schema::drop('organizations');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'The database-per-organization conversion is not reversible. Restore the pre-migration backup instead.',
        );
    }

    private function assertDatabaseCanBecomeSingleOrganization(): ?object
    {
        $organizations = DB::table('organizations')->orderBy('id')->get();

        if ($organizations->count() > 1) {
            throw new RuntimeException(
                'This database contains multiple organizations. Split it into one database per organization before running this migration.',
            );
        }

        $this->assertNormalizedNamesAreUnique('organization_artists', 'artist');
        $this->assertNormalizedNamesAreUnique('artist_labels', 'artist label');

        return $organizations->first();
    }

    private function assertNormalizedNamesAreUnique(string $table, string $description): void
    {
        $names = [];

        foreach (DB::table($table)->orderBy('id')->pluck('name') as $name) {
            $nameKey = $this->normalizeName($name);

            if (isset($names[$nameKey])) {
                throw new RuntimeException(
                    "Duplicate case-insensitive {$description} name [{$name}] must be resolved before migration.",
                );
            }

            $names[$nameKey] = true;
        }
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
};
