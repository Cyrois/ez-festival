<?php

namespace Tests\Feature;

use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MealAssignmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forward_migration_merges_live_grants_and_claims_and_preserves_removed_used_snapshots(): void
    {
        $migration = require database_path('migrations/2026_10_07_000003_unify_meal_assignments.php');
        $migration->down();
        $event = app(EventService::class)->create(['name' => 'Existing', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $member = $event->teamEngagements()->create(['person_id' => Person::create(['name' => 'Recipient', 'email' => 'recipient@example.test'])->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $location = $event->locations()->create(['name' => 'Kitchen']);
        $role = Role::create(['name' => 'Crew']);
        $meal = $event->meals()->create(['name' => 'Lunch', 'meal_type_id' => $event->mealTypes()->where('name', 'Lunch')->sole()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00:00', 'ends_at' => '14:00:00']);
        $origins = [];
        foreach (['Used', 'Unused', 'Removed'] as $name) {
            $shift = $event->shifts()->create(['name' => $name, 'location_id' => $location->id, 'starts_at' => '2026-10-01 09:00', 'ends_at' => '2026-10-01 18:00']);
            $roster = $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $role->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
            $row = $shift->meals()->create(['meal_id' => $meal->id]);
            DB::table('shift_meal_people')->insert(['shift_meal_id' => $row->id, 'shift_assignment_id' => $roster->id]);
            $origins[$name] = $shift->id;
            if ($name !== 'Unused') {
                DB::table('meal_claims')->insert([
                    'event_id' => $event->id, 'meal_id' => $meal->id, 'team_engagement_id' => $member->id, 'meal_type_id' => $meal->meal_type_id,
                    'shift_meal_id' => $row->id, 'source_shift_id' => $shift->id, 'meal_name' => 'Original lunch', 'meal_date' => '2026-10-01',
                    'starts_at' => '12:00:00', 'ends_at' => '14:00:00', 'shift_location_name' => 'Original kitchen',
                    'shift_starts_at' => '2026-10-01 09:00', 'shift_ends_at' => '2026-10-01 18:00',
                    'claimed_by' => $user->id, 'claimed_at' => '2026-10-01 12:05',
                    'warning_overridden_by' => $name === 'Removed' ? $user->id : null,
                    'warning_overridden_at' => $name === 'Removed' ? '2026-10-01 12:05' : null,
                ]);
            }
            if ($name === 'Removed') {
                $shift->delete();
            }
        }
        $migration->up();
        $this->assertFalse(Schema::hasTable('meal_claims'));
        $this->assertFalse(Schema::hasTable('shift_meal_people'));
        $this->assertDatabaseCount('meal_assignments', 3);
        $used = MealAssignment::query()->where('source_shift_id', $origins['Used'])->sole();
        $this->assertTrue($used->is_active);
        $this->assertSame('Original lunch', $used->meal_name);
        $this->assertSame('Original kitchen', $used->shift_location_name);
        $this->assertSame($user->id, $used->claimed_by);
        $this->assertNotNull($used->claim_token);
        $unused = MealAssignment::query()->where('source_shift_id', $origins['Unused'])->sole();
        $this->assertTrue($unused->is_active);
        $this->assertNull($unused->claimed_at);
        $this->assertSame('Lunch', $unused->meal_name);
        $removed = MealAssignment::query()->where('source_shift_id', $origins['Removed'])->sole();
        $this->assertFalse($removed->is_active);
        $this->assertNull($removed->shift_meal_id);
        $this->assertNull($removed->shift_assignment_id);
        $this->assertSame($user->id, $removed->warning_overridden_by);
        $this->assertNotNull($removed->claimed_at);
        // A rollback that can be represented in the old schema preserves grant and claim data.
        $migration->down();
        $this->assertDatabaseCount('meal_claims', 2);
        $this->assertDatabaseCount('shift_meal_people', 2);
        $migration->up();
        $this->assertDatabaseCount('meal_assignments', 3);
    }

    public function test_rollback_refuses_to_discard_direct_person_assignments(): void
    {
        $event = app(EventService::class)->create(['name' => 'Direct', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $member = $event->teamEngagements()->create(['person_id' => Person::create(['name' => 'Recipient', 'email' => 'recipient@example.test'])->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $meal = $event->meals()->create(['name' => 'Lunch', 'meal_type_id' => $event->mealTypes()->firstOrFail()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00:00', 'ends_at' => '14:00:00']);
        $assignment = app(MealAssignmentService::class)->assign($event, $member, $meal);
        $migration = require database_path('migrations/2026_10_07_000003_unify_meal_assignments.php');
        try {
            $migration->down();
            $this->fail('Rollback must not silently lose direct assignments.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('forward migration', $exception->getMessage());
        }
        $this->assertModelExists($assignment);
        $this->assertFalse(Schema::hasTable('meal_claims'));
    }
}
