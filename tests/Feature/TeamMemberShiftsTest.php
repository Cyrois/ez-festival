<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamMemberShiftsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    private Event $event;

    private TeamEngagement $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Festival');
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->member = $this->member($this->event);
        $this->actingAs($this->user);
    }

    public function test_rows_use_the_members_own_day_hours_and_assignment_role_in_this_event_only(): void
    {
        $role = Role::create(['name' => 'Gate crew']);
        $this->member->update(['role_id' => Role::create(['name' => 'Event admin'])->id]);
        $shift = $this->shift($this->event);
        $assignment = $this->assign($shift, $this->member, $role);
        $this->assign($shift, $this->member($this->event));
        $foreignEvent = $this->event('Other festival');
        $foreignShift = $this->shift($foreignEvent);
        $foreignMember = TeamEngagement::create([
            'event_id' => $foreignEvent->id, 'person_id' => $this->member->person_id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $this->assign($foreignShift, $foreignMember);
        // The read query also defends against an inconsistent cross-event assignment.
        $this->assign($foreignShift, $this->member);

        $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page
            ->where('canViewShifts', true)->missing('shifts')->missing('engagement.shift_assignments'));
        $this->getJson($this->url())->assertOk()->assertExactJson([
            'draw' => 0, 'recordsTotal' => 1, 'recordsFiltered' => 1,
            'data' => [[
                'id' => $assignment->id, 'shift_id' => $shift->id, 'day' => '2026-10-03', 'location' => 'Gate',
                'starts_at' => '2026-10-03T00:30', 'ends_at' => '2026-10-03T02:00', 'role_name' => 'Gate crew',
            ]],
        ]);
    }

    public function test_scheduling_assignment_hours_changes_and_removal_are_reflected_on_next_load(): void
    {
        $shift = $this->shift($this->event);
        $this->getJson($this->url())->assertJsonCount(0, 'data');
        $this->post(route('team.shifts.assignments.store', [$this->event, $shift]), [
            'extra' => true, 'team_engagement_id' => $this->member->id, 'hours_mode' => 'custom',
            'starts_at' => '2026-10-03T00:30', 'ends_at' => '2026-10-03T02:00',
        ])->assertSessionHasNoErrors();
        $assignment = $shift->assignments()->sole();
        $this->getJson($this->url())->assertJsonCount(1, 'data')->assertJsonPath('data.0.role_name', null);

        $this->put(route('team.shifts.update', [$this->event, $shift]), [
            'name' => 'Updated', 'location_id' => $shift->location_id,
            'starts_at' => '2026-10-02T22:00', 'ends_at' => '2026-10-03T03:00',
            'assignment_updates' => [[
                'id' => $assignment->id, 'hours_mode' => 'custom',
                'starts_at' => '2026-10-03T00:45', 'ends_at' => '2026-10-03T01:45',
            ]],
        ])->assertSessionHasNoErrors();
        $this->getJson($this->url())->assertJsonPath('data.0.starts_at', '2026-10-03T00:45')
            ->assertJsonPath('data.0.ends_at', '2026-10-03T01:45');
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $shift, $assignment]))->assertSessionHasNoErrors();
        $this->getJson($this->url())->assertJsonPath('recordsTotal', 0)->assertJsonCount(0, 'data');
    }

    public function test_saved_assignments_remain_visible_after_member_status_and_role_changes(): void
    {
        $shift = $this->shift($this->event);
        $role = Role::create(['name' => 'Saved position']);
        $this->assign($shift, $this->member, $role);
        $this->member->update(['status' => 'declined', 'role_id' => null]);
        $role->update(['active' => false]);
        $this->getJson($this->url())->assertJsonCount(1, 'data')->assertJsonPath('data.0.role_name', 'Saved position');
    }

    public function test_team_view_without_scheduling_access_omits_data_and_never_queries_assignments(): void
    {
        $this->assign($this->shift($this->event), $this->member);
        $this->grantRoleAccess($this->user, ['team.view']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page
            ->where('canViewShifts', false)->missing('shifts')->missing('engagement.shift_assignments'));
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'shift_assignments')));
        DB::disableQueryLog();
        $this->getJson($this->url())->assertForbidden()->assertJsonMissingPath('data');
    }

    public function test_member_page_and_data_endpoint_require_both_view_permissions(): void
    {
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->get(route('team.members.show', $this->member))->assertForbidden();
        $this->getJson($this->url())->assertForbidden();
        $this->grantRoleAccess($this->user, ['team.edit']);
        $this->getJson($this->url())->assertForbidden();
        $this->grantRoleAccess($this->user, ['team.view', 'scheduling.edit']);
        $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('canViewShifts', true));
        $this->getJson($this->url())->assertOk();
    }

    public function test_revoking_scheduling_permission_applies_on_the_next_request(): void
    {
        $this->grantRoleAccess($this->user, ['team.view', 'scheduling.view']);
        $this->getJson($this->url())->assertOk();
        $this->user->person->teamEngagements()->where('event_id', $this->event->id)->firstOrFail()->role->update(['permissions' => ['team.view']]);
        $this->getJson($this->url())->assertForbidden();
        $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('canViewShifts', false));
    }

    public function test_locked_events_are_readable_and_foreign_member_records_are_refused(): void
    {
        $this->assign($this->shift($this->event), $this->member);
        $this->event->lock();
        foreach ([true, false] as $admin) {
            if (! $admin) {
                $this->grantRoleAccess($this->user, ['team.view', 'scheduling.view']);
            }
            $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page
                ->where('canViewShifts', true)->where('canWrite', false));
            $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
        }
        $this->grantAdminAccess($this->user);
        $foreign = $this->member($this->event('Other festival'));
        $this->get(route('team.members.show', $foreign))->assertNotFound();
        $this->getJson(route('team.members.shifts', $foreign))->assertNotFound();
    }

    public function test_paging_search_and_order_are_scoped_and_use_raw_values(): void
    {
        $shift = $this->shift($this->event);
        foreach (range(1, 26) as $number) {
            $other = $shift->replicate();
            $other->save();
            $this->assign($other, $this->member);
        }
        $namedAssignment = $this->assign($shift, $this->member, Role::create(['name' => 'Gate % crew']));
        $namedAssignment->update(['starts_at' => '2026-10-03T00:45']);
        $shift->update(['location_id' => $this->event->locations()->create(['name' => 'Zulu gate'])->id]);
        $this->getJson($this->url())->assertJsonPath('recordsTotal', 27)->assertJsonCount(25, 'data');
        $this->getJson($this->url(['start' => 25]))->assertJsonCount(2, 'data');
        $this->getJson($this->url(['draw' => 4, 'search' => ['value' => '%']]))->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 27)->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.role_name', 'Gate % crew');
        $this->getJson($this->url(['search' => ['value' => 'gAtE % CREW']]))->assertJsonPath('recordsFiltered', 1);
        $this->getJson($this->url(['search' => ['value' => '00:30']]))->assertJsonPath('recordsFiltered', 26);
        foreach ([0, 1, 2, 3] as $column) {
            $this->getJson($this->url(['order' => [['column' => $column, 'dir' => 'desc']]]))
                ->assertJsonPath('data.0.id', $namedAssignment->id)->assertJsonCount(25, 'data');
        }
        $this->getJson($this->url(['search' => ['value' => 'not present']]))->assertJsonPath('recordsFiltered', 0)->assertJsonCount(0, 'data');
    }

    public function test_invalid_table_parameters_are_rejected_by_the_form_request(): void
    {
        foreach ([['length' => -1], ['start' => -1], ['draw' => -1], ['search' => ['value' => str_repeat('a', 256)]], ['order' => [['column' => 4, 'dir' => 'asc']]], ['order' => [['column' => 0, 'dir' => 'invalid']]]] as $parameters) {
            $this->getJson($this->url($parameters))->assertUnprocessable()->assertJsonStructure(['errors']);
        }
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'America/Vancouver']);
    }

    private function member(Event $event): TeamEngagement
    {
        $person = Person::create(['name' => 'Member', 'email' => fake()->unique()->safeEmail()]);

        return TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function shift(Event $event): Shift
    {
        return app(ShiftService::class)->create($event, [
            'location_id' => $event->locations()->create(['name' => 'Gate'])->id,
            'starts_at' => '2026-10-02T22:00', 'ends_at' => '2026-10-03T03:00',
        ]);
    }

    private function assign(Shift $shift, TeamEngagement $member, ?Role $role = null): ShiftAssignment
    {
        return $shift->assignments()->create([
            'team_engagement_id' => $member->id, 'role_id' => $role?->id,
            'starts_at' => '2026-10-03T00:30', 'ends_at' => '2026-10-03T02:00',
        ]);
    }

    private function url(array $parameters = []): string
    {
        return route('team.members.shifts', $this->member).($parameters === [] ? '' : '?'.http_build_query($parameters));
    }
}
