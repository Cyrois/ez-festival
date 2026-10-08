<?php

namespace Tests\Feature;

use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MealAssignmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forward_migration_converts_shift_grants_into_person_assignments(): void
    {
        $migration = require database_path('migrations/2026_10_07_000003_unify_meal_assignments.php');
        $migration->down();
        $event = app(EventService::class)->create(['name' => 'Existing', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $member = $event->teamEngagements()->create(['person_id' => Person::create(['name' => 'Recipient', 'email' => 'recipient@example.test'])->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $location = $event->locations()->create(['name' => 'Kitchen']);
        $role = Role::create(['name' => 'Crew']);
        $meal = $event->meals()->create(['name' => 'Lunch', 'meal_type_id' => $event->mealTypes()->where('name', 'Lunch')->sole()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00:00', 'ends_at' => '14:00:00']);
        $origins = [];
        foreach (['First', 'Second'] as $name) {
            $shift = $event->shifts()->create(['name' => $name, 'location_id' => $location->id, 'starts_at' => '2026-10-01 09:00', 'ends_at' => '2026-10-01 18:00']);
            $roster = $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $role->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
            $row = $shift->meals()->create(['meal_id' => $meal->id]);
            DB::table('shift_meal_people')->insert(['shift_meal_id' => $row->id, 'shift_assignment_id' => $roster->id]);
            $origins[$name] = $shift->id;
        }
        $migration->up();
        $this->assertFalse(Schema::hasTable('shift_meal_people'));
        $this->assertDatabaseCount('meal_assignments', 2);
        foreach ($origins as $shiftId) {
            $assignment = MealAssignment::query()->where('source_shift_id', $shiftId)->sole();
            $this->assertSame($member->id, $assignment->team_engagement_id);
            $this->assertSame($meal->id, $assignment->meal_id);
            $this->assertTrue($assignment->is_active);
            $this->assertSame('Lunch', $assignment->meal_name);
            $this->assertSame('Kitchen', $assignment->shift_location_name);
            $this->assertNotNull($assignment->shift_meal_id);
            $this->assertNotNull($assignment->shift_assignment_id);
            $this->assertNull($assignment->claimed_at);
        }
        $migration->down();
        $this->assertDatabaseCount('shift_meal_people', 2);
        $migration->up();
        $this->assertDatabaseCount('meal_assignments', 2);
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
        $this->assertTrue(Schema::hasTable('meal_assignments'));
    }
}
