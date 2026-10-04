<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditShiftTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function invalidTimes(): array
    {
        return [['2026-03-08T02:30', '2026-03-08T04:00'], ['2026-11-01T01:30', '2026-11-01T03:00']];
    }

    #[DataProvider('invalidTimes')]
    public function test_shift_forms_reject_missing_or_repeated_local_times(string $start, string $end): void
    {
        [$user,$event,$location] = $this->context();
        $payload = ['name' => 'Invalid', 'location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end];
        $this->actingAs($user)->postJson(route('team.shifts.store', $event), $payload)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertSame(0, $event->shifts()->count());
        $this->getJson(route('team.shifts.create', ['location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end]))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public static function transitions(): array
    {
        return [['2026-03-08T01:30', '2026-03-08T03:30', '2026-03-08T03:00', 45, 60], ['2026-11-01T00:30', '2026-11-01T02:30', '2026-11-01T02:00', 165, 180]];
    }

    #[DataProvider('transitions')]
    public function test_roster_hours_and_overlap_minutes_use_actual_elapsed_time(string $start, string $end, string $breakStart, int $scheduled, int $overlap): void
    {
        [$user,$event,$location] = $this->context();
        $role = Role::create(['name' => 'Crew']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => Person::create(['name' => 'Worker', 'email' => 'worker@example.test'])->id, 'role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $data = ['name' => 'Transition', 'location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end, 'slots' => [['role_id' => $role->id, 'needed' => 1]], 'breaks' => [['starts_at' => $breakStart, 'duration_minutes' => 15]]];
        $shift = app(ShiftService::class)->create($event, $data);
        $other = app(ShiftService::class)->create($event, [...$data, 'name' => 'Other']);
        foreach ([$shift, $other] as $row) {
            app(ShiftAssignmentService::class)->create($row, ['shift_role_slot_id' => $row->roleSlots()->sole()->id, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift']);
        }
        $this->actingAs($user)->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('event.timezone', 'America/Vancouver')->where('shift.assignments.0.scheduled_minutes', $scheduled)->where('shift.assignments.0.overlaps.0.overlap_minutes', $overlap));
    }

    public function test_assignment_and_break_inputs_share_timezone_validation(): void
    {
        [$user,$event,$location] = $this->context();
        $role = Role::create(['name' => 'Crew']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => Person::create(['name' => 'Worker', 'email' => 'worker@example.test'])->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $data = ['name' => 'Spring', 'location_id' => $location->id, 'starts_at' => '2026-03-08T01:30', 'ends_at' => '2026-03-08T03:30', 'slots' => [['role_id' => $role->id, 'needed' => 1]]];
        $shift = app(ShiftService::class)->create($event, $data);
        $assignment = ['shift_role_slot_id' => $shift->roleSlots()->sole()->id, 'team_engagement_id' => $member->id, 'hours_mode' => 'custom', 'starts_at' => '2026-03-08T02:30', 'ends_at' => '2026-03-08T03:15'];
        $this->actingAs($user)->postJson(route('team.shifts.assignments.store', [$event, $shift]), $assignment)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->putJson(route('team.shifts.update', [$event, $shift]), [...$data, 'breaks' => [['starts_at' => '2026-03-08T02:30', 'duration_minutes' => 15]]])->assertUnprocessable()->assertJsonValidationErrors('breaks.0.starts_at');
        $this->putJson(route('team.shifts.update', [$event, $shift]), [...$data, 'assignment_additions' => [$assignment]])->assertUnprocessable()->assertJsonValidationErrors('assignment_additions.0.starts_at');
        $this->assertSame(0, $shift->assignments()->count());
        $this->assertSame(0, $shift->breaks()->count());
    }

    private function context(): array
    {
        $this->withoutVite();
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2026-03-01', 'ends_on' => '2026-11-30', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);

        return [$user, $event, $event->locations()->create(['name' => 'Stage'])];
    }
}
