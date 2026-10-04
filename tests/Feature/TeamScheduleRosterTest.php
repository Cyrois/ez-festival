<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Location;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Repositories\ShiftAssignmentRepository;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamScheduleRosterTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Location $location;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        $this->location = $this->event->locations()->create(['name' => 'Main stage']);
        $this->role = Role::create(['name' => 'Volunteer']);
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_roster_matches_shift_page_including_order_open_counts_extras_and_overlaps_without_private_fields(): void
    {
        $shift = $this->shift();
        $first = $shift->roleSlots()->first();
        $second = $shift->roleSlots()->create(['role_id' => $this->role->id, 'needed' => 2, 'sort_order' => 1]);
        $removed = $shift->roleSlots()->create(['role_id' => $this->role->id, 'needed' => 1, 'sort_order' => 2]);
        $member = $this->member('Alpha');
        $a = $this->assign($shift, $first->id, $member, '2026-10-01T10:00', '2026-10-01T13:00');
        $b = $this->assign($shift, $first->id, $this->member('Beta'));
        $a->forceFill(['created_at' => '2026-09-30 12:00'])->save();
        $b->forceFill(['created_at' => '2026-09-30 11:00'])->save();
        $this->assign($shift, $second->id, $this->member('Gamma'));
        $detached = $this->assign($shift, $removed->id, $this->member('Detached'));
        $removed->delete();
        $other = $this->shift(['name' => 'Show run', 'starts_at' => '2026-10-01T12:30', 'ends_at' => '2026-10-01T14:00']);
        $this->assign($other, $other->roleSlots()->first()->id, $member, '2026-10-01T12:30', '2026-10-01T14:00');
        $roster = $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.id', $shift->id)
            ->assertJsonPath('data.0.slots.0.id', $first->id)->assertJsonPath('data.0.slots.1.id', $second->id)
            ->assertJsonPath('data.0.slots.1.open_count', 1)->assertJsonPath('data.0.filled_count', 2)
            ->assertJsonPath('data.0.extra_count', 2)->assertJsonPath('data.0.assignments.0.id', $b->id)
            ->assertJsonPath('data.0.assignments.1.id', $a->id)->assertJsonPath('data.0.assignments.1.is_extra', true)
            ->assertJsonPath('data.0.assignments.1.overlaps.0.shift_name', 'Show run')
            ->assertJsonPath('data.0.assignments.1.overlaps.0.overlap_minutes', 30)
            ->assertJsonPath('data.0.assignments.3.id', $detached->id)->assertJsonPath('data.0.assignments.3.is_extra', true);
        $expected = app(ShiftAssignmentRepository::class)->loadRoster($shift->fresh());
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments', $roster->json('data.0.assignments'))->where('shift.slots', $roster->json('data.0.slots')));
        $this->assertSame($expected->assignments->pluck('is_extra')->all(), array_column($roster->json('data.0.assignments'), 'is_extra'));
        $this->assertDoesNotMatchRegularExpression('/"(?:email|phone|hourly_pay|pay|team_engagement_id)"/', $roster->getContent());
        $roster->assertDontSee('@example.test', false);
    }

    public function test_only_selected_location_day_and_event_with_overnight_people_retained_on_both_days(): void
    {
        $late = $this->shift(['starts_at' => '2026-10-01T23:00', 'ends_at' => '2026-10-02T02:00']);
        $this->assign($late, $late->roleSlots()->first()->id, $this->member('Early hours'), '2026-10-01T23:00', '2026-10-01T23:30');
        $early = $this->shift();
        $otherLocation = $this->event->locations()->create(['name' => 'Gate']);
        $this->shift(['location_id' => $otherLocation->id]);
        $this->shift(['starts_at' => '2026-09-30T23:00', 'ends_at' => '2026-10-01T00:00']);
        $this->shift(['starts_at' => '2026-10-02T00:00', 'ends_at' => '2026-10-02T01:00']);
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $early->id)->assertJsonPath('data.1.id', $late->id);
        $this->getJson($this->url(['date' => '2026-10-02']))->assertOk()
            ->assertJsonPath('data.0.id', $late->id)->assertJsonPath('data.0.assignments.0.name', 'Early hours')
            ->assertJsonPath('data.0.assignments.0.ends_at', '2026-10-01T23:30');
        $foreignEvent = Event::create(['name' => 'Foreign', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        $foreign = $foreignEvent->locations()->create(['name' => 'Other']);
        $this->getJson($this->url(['location_id' => $foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->getJson(route('team.scheduling.roster', ['date' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->getJson($this->url(['date' => '2026-02-30']))->assertUnprocessable();
        $this->getJson($this->url(['date' => '2026-10-05']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_all_rosters_for_the_selected_day_are_loaded_in_batches(): void
    {
        for ($index = 0; $index < 27; $index++) {
            $shift = $this->shift();
            $this->assign($shift, $shift->roleSlots()->first()->id, $this->member('Crew '.$index));
        }
        DB::enableQueryLog();
        $response = $this->getJson($this->url())->assertOk()->assertJsonCount(27, 'data')->assertJsonMissingPath('meta');
        foreach ($response->json('data') as $shift) {
            $this->assertCount(1, $shift['assignments']);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(35, count($queries));
    }

    public function test_view_and_assignment_permissions_and_locked_event_apply_to_roster(): void
    {
        $shift = $this->shift();
        $payload = ['shift_role_slot_id' => $shift->roleSlots()->first()->id, 'team_engagement_id' => $this->member('Crew')->id, 'hours_mode' => 'full_shift'];
        $candidates = route('team.shifts.assignment-candidates', ['shift' => $shift, ...array_diff_key($payload, ['team_engagement_id' => true])]);
        $store = route('team.shifts.assignments.store', [$this->event, $shift]);
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->getJson($this->url())->assertForbidden();
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->getJson($this->url())->assertOk();
        $this->getJson($candidates)->assertForbidden();
        $this->postJson($store, $payload)->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->event->lock();
        $this->getJson($this->url())->assertOk();
        $this->getJson($candidates)->assertForbidden();
        $this->postJson($store, $payload)->assertForbidden();
    }

    public function test_assignment_from_roster_returns_to_same_day_and_location_with_updated_rows(): void
    {
        $shift = $this->shift();
        $slot = $shift->roleSlots()->first();
        $member = $this->member('New crew');
        $this->post(route('team.shifts.assignments.store', [$this->event, $shift]), [
            'shift_role_slot_id' => $slot->id, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift',
            'return_to_schedule' => true, 'return_tab' => 'schedule', 'schedule_date' => '2026-10-01', 'schedule_location_id' => $this->location->id, 'schedule_view' => 'location_shifts',
        ])->assertSessionHasNoErrors()->assertRedirect(route('team.scheduling', ['tab' => 'schedule', 'date' => '2026-10-01', 'view' => 'location_shifts', 'location_id' => $this->location->id]));
        $this->getJson($this->url())->assertJsonPath('data.0.slots.0.open_count', 0)->assertJsonPath('data.0.assignments.0.name', 'New crew');
        $this->get(route('team.shifts.show', ['shift' => $shift, 'schedule_location_id' => $this->location->id]))
            ->assertInertia(fn (Assert $page) => $page->where('returnContext.schedule_location_id', (string) $this->location->id));
    }

    private function url(array $data = []): string
    {
        return route('team.scheduling.roster', ['date' => '2026-10-01', 'location_id' => $this->location->id, ...$data]);
    }

    private function shift(array $data = []): Shift
    {
        return app(ShiftService::class)->create($this->event, ['name' => 'Morning', 'location_id' => $this->location->id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', 'slots' => [['role_id' => $this->role->id, 'needed' => 1]], ...$data]);
    }

    private function member(string $name): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => 'private-'.mb_strtolower(str_replace(' ', '-', $name)).'@example.test']);

        return TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function assign(Shift $shift, int $slot, TeamEngagement $member, ?string $start = null, ?string $end = null)
    {
        return $shift->assignments()->create(['team_engagement_id' => $member->id, 'shift_role_slot_id' => $slot, 'role_id' => $this->role->id, 'starts_at' => $start ?? $shift->starts_at, 'ends_at' => $end ?? $shift->ends_at]);
    }
}
