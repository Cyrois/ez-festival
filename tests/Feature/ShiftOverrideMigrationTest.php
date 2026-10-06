<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Services\ShiftAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ShiftOverrideMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_forward_migration_preserves_existing_assignments_breaks_constraints_and_sequence(): void
    {
        $migration = require database_path('migrations/2026_10_05_000002_allow_shift_assignment_overrides.php');
        $migration->down();
        $event = Event::create(['name' => 'Migration event', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02', 'timezone' => 'America/Vancouver']);
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = Role::create(['name' => 'Crew']);
        $person = Person::create(['name' => 'Legacy person', 'email' => 'legacy@example.test']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $shift = Shift::create(['event_id' => $event->id, 'location_id' => $location->id, 'name' => 'Legacy shift', 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00']);
        $slot = $shift->roleSlots()->create(['role_id' => $role->id, 'needed' => 1, 'sort_order' => 0]);
        $assignment = app(ShiftAssignmentService::class)->create($shift, ['team_engagement_id' => $member->id, 'shift_role_slot_id' => $slot->id, 'hours_mode' => 'full_shift']);
        $break = $assignment->breaks()->create(['starts_at' => '2026-10-01T12:00', 'duration_minutes' => 15, 'sort_order' => 0]);
        $before = $assignment->fresh()->toArray();
        $deletedRole = Role::create(['name' => 'Deleted role']);
        $deletedRoleId = $deletedRole->id;
        $deletedRole->delete();

        $second = Person::create(['name' => 'Next person', 'email' => 'next@example.test']);
        $nextMember = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $second->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $deleted = app(ShiftAssignmentService::class)->create($shift, ['team_engagement_id' => $nextMember->id, 'shift_role_slot_id' => $slot->id, 'hours_mode' => 'full_shift']);
        $lastId = $deleted->id;
        $deleted->delete();

        $migration->up();
        $this->assertSame($before, $assignment->fresh()->toArray());
        $this->assertModelExists($break);
        $this->assertSame($assignment->id, $break->fresh()->shift_assignment_id);
        foreach (Schema::getIndexes('shift_assignments') as $index) {
            $this->assertLessThanOrEqual(63, strlen($index['name']));
        }

        foreach ([
            fn () => $assignment->update(['ends_at' => '2026-10-01T09:00']),
            fn () => DB::table('team_engagements')->where('id', $member->id)->delete(),
            fn () => $shift->assignments()->create($assignment->only(['team_engagement_id', 'shift_role_slot_id', 'role_id', 'starts_at', 'ends_at'])),
            fn () => $assignment->update(['role_id' => $deletedRoleId]),
        ] as $write) {
            try {
                DB::transaction($write);
                $this->fail('The original assignment constraints must still reject invalid writes.');
            } catch (QueryException) {
                $this->assertSame($before, $assignment->fresh()->toArray());
                $this->assertModelExists($break);
            }
        }
        $override = app(ShiftAssignmentService::class)->create($shift, ['override' => true, 'team_engagement_id' => $nextMember->id, 'hours_mode' => 'full_shift']);
        $this->assertGreaterThan($lastId, $override->id);
        $this->assertNull($override->role_id);
        try {
            $migration->down();
            $this->fail('Rollback must not discard overrides or invent roles.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('role-free shift overrides', $exception->getMessage());
            $this->assertModelExists($override);
            $this->assertModelExists($break);
        }
        $override->delete();
        $migration->down();
        $this->assertModelExists($break);
        $migration->up();
        $shift->delete();
        $this->assertModelMissing($assignment);
        $this->assertModelMissing($break);
    }
}
