<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Repositories\ShiftAssignmentRepository;
use App\Repositories\ShiftRepository;
use App\Services\EventService;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamShiftAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Event $event;

    private Shift $shift;

    private Role $role;

    private int $slotId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->event = $this->event('Festival');
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user->setCurrentEvent($this->event);
        $this->role = Role::create(['name' => 'Gate crew']);
        $this->shift = $this->shift($this->event);
        $this->slotId = $this->shift->roleSlots()->sole()->id;
        $this->actingAs($this->user);
    }

    public function test_full_custom_and_extra_assignments_leave_team_data_unchanged(): void
    {
        $otherRole = Role::create(['name' => 'Artist admin', 'permissions' => ['artists.edit']]);
        $members = [$this->member('Alpha', $otherRole), $this->member('Beta'), $this->member('Gamma')];
        $before = $members[0]->fresh()->toArray();
        $this->post($this->storeUrl(), $this->payload($members[0]))->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Alpha assigned as Gate crew.');
        $this->post($this->storeUrl(), $this->payload($members[1], ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00']))->assertSessionHasNoErrors();
        $this->post($this->storeUrl(), $this->payload($members[2]))->assertSessionHasNoErrors();
        $this->assertSame($before, $members[0]->fresh()->toArray());
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignment_count', 3)->where('shift.filled_count', 2)->where('shift.extra_count', 1)
            ->where('shift.slots.0.open_count', 0)->where('shift.slots.0.assigned_count', 3)
            ->where('shift.assignments.0.role_name', 'Gate crew')->where('shift.assignments.0.starts_at', '2026-10-01T10:00')
            ->where('shift.assignments.1.ends_at', '2026-10-01T12:00')->where('shift.assignments.2.is_extra', true)
            ->missing('shift.assignments.0.email')->missing('shift.assignments.0.team_engagement_id')
            ->missing('shift.assignments.0.hourly_pay')->missing('candidates'));
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.filled_count', 2)
            ->assertJsonPath('data.0.extra_count', 1)->assertJsonMissingPath('data.0.assignments');
    }

    public function test_duplicate_role_requirements_count_separately_and_removal_promotes_extras(): void
    {
        $second = $this->shift->roleSlots()->create(['role_id' => $this->role->id, 'needed' => 1, 'sort_order' => 1]);
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->assign($this->member($name));
        }
        $roster = app(ShiftAssignmentRepository::class)->loadRoster($this->shift->fresh());
        $this->assertSame([3, 0], $roster->roleSlots->pluck('assigned_count')->all());
        $removed = $roster->assignments->first();
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $removed]))->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 2)->where('shift.extra_count', 0)->where('shift.slots.1.open_count', 1)
            ->where('shift.assignments.1.is_extra', false));
        $this->post($this->storeUrl(), $this->payload($this->member('Delta'), ['shift_role_slot_id' => $second->id]))->assertSessionHasNoErrors();
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.filled_count', 3);
    }

    public function test_quantity_reduction_preserves_every_assignment_and_uses_creation_order(): void
    {
        $first = $this->assign($this->member('Alpha'));
        $second = $this->assign($this->member('Beta'));
        $first->forceFill(['created_at' => '2026-09-30 11:00'])->save();
        $second->forceFill(['created_at' => '2026-09-30 10:00'])->save();
        $this->put($this->updateUrl(), $this->shiftPayload(['slots' => [['id' => $this->slotId, 'role_id' => $this->role->id, 'needed' => 1]]]))->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 1)->where('shift.extra_count', 1)
            ->where('shift.assignments.0.id', $second->id)->where('shift.assignments.0.is_extra', false)
            ->where('shift.assignments.1.id', $first->id)->where('shift.assignments.1.is_extra', true));
    }

    public function test_removing_and_changing_requirements_detaches_assignments_without_relabelling(): void
    {
        $assignment = $this->assign($this->member('Alpha'));
        $original = $assignment->fresh()->only(['id', 'shift_id', 'team_engagement_id', 'shift_role_slot_id', 'role_id', 'starts_at', 'ends_at']);
        $newRole = Role::create(['name' => 'Sound']);
        $this->put($this->updateUrl(), $this->shiftPayload(['slots' => [['id' => $this->slotId, 'role_id' => $newRole->id, 'needed' => 1]]]))->assertSessionHasNoErrors();
        $this->assertEquals([...$original, 'shift_role_slot_id' => null], $assignment->fresh()->only(array_keys($original)));
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 0)->where('shift.extra_count', 1)->where('shift.assignments.0.role_name', 'Gate crew'));
        $second = $this->assign($this->member('Beta'));
        $this->put($this->updateUrl(), $this->shiftPayload(['slots' => []]))->assertSessionHasNoErrors();
        $this->assertNull($second->fresh()->shift_role_slot_id);
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.total_needs', 0)
            ->assertJsonPath('data.0.filled_count', 0)->assertJsonPath('data.0.assignment_count', 2)->assertJsonPath('data.0.extra_count', 2);
    }

    public function test_shift_containment_failure_is_atomic_and_extension_preserves_full_shift_hours(): void
    {
        $assignment = $this->assign($this->member('Alpha'));
        $data = $this->shiftPayload(['name' => 'Changed', 'starts_at' => '2026-10-01T11:00', 'slots' => []]);
        $this->put($this->updateUrl(), $data)->assertSessionHasErrors('ends_at');
        $this->assertSame('Show run', $this->shift->fresh()->name);
        $this->assertDatabaseHas('shift_role_slots', ['id' => $this->slotId]);
        try {
            app(ShiftService::class)->update($this->shift, $data);
            $this->fail('Containment must be rechecked in the transaction.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Alpha', $exception->errors()['ends_at'][0]);
        }
        $this->put($this->updateUrl(), $this->shiftPayload(['starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T15:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01 10:00:00', $assignment->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 14:00:00', $assignment->fresh()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_candidates_are_paginated_suggested_first_and_do_not_expose_personal_data(): void
    {
        $this->member('Aardvark');
        $matching = $this->member('Zulu', $this->role);
        $abigail = $this->member('Abigail', $this->role);
        $group = Group::create(['event_id' => $this->event->id, 'name' => 'Stage']);
        $abigail->update(['group_id' => $group->id]);
        $this->member('Declined', $this->role, 'declined');
        $this->member('Foreign', $this->role, 'hired', $this->event('Other'));
        $this->getJson($this->candidateUrl(['per_page' => 1]))->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.role_name', 'Gate crew')->assertJsonPath('data.0.group_name', 'Stage')
            ->assertJsonPath('data.0.name', 'Abigail')->assertJsonPath('data.0.suggested', true)
            ->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.role_id')->assertJsonMissingPath('data.0.permissions');
        $this->getJson($this->candidateUrl(['page' => 2, 'per_page' => 1]))->assertJsonPath('data.0.id', $matching->id);
        $this->getJson($this->candidateUrl(['page' => 3, 'per_page' => 1]))->assertJsonPath('data.0.name', 'Aardvark')->assertJsonPath('data.0.suggested', false);
        $this->getJson($this->candidateUrl())->assertJsonPath('meta.per_page', 5);
        $this->assign($matching);
        $this->getJson($this->candidateUrl(['search' => 'zULu']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.on_shift', true);
        $this->getJson($this->candidateUrl(['per_page' => 6]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($this->candidateUrl(['search' => '%']))->assertJsonPath('meta.total', 0);
    }

    public function test_invalid_hours_missing_fields_and_unexpected_fields_never_write(): void
    {
        $member = $this->member('Alpha');
        foreach ([
            ['hours_mode' => 'invalid'], ['team_engagement_id' => null], ['shift_role_slot_id' => null],
            ['hours_mode' => 'custom'], ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T12:00'],
            ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T12:00'],
            ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T13:00', 'ends_at' => '2026-10-01T12:00'],
            ['hours_mode' => 'custom', 'starts_at' => 'bad', 'ends_at' => '2026-10-01T12:00'],
            ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T15:00'],
            ['role_id' => $this->role->id], ['extra' => true], ['event_access' => true], ['assignment_count' => 4],
            ['starts_at' => '2026-10-01T10:00'],
        ] as $change) {
            $this->post($this->storeUrl(), $this->payload($member, $change))->assertSessionHasErrors();
            $this->assertDatabaseCount('shift_assignments', 0);
        }
        $this->getJson($this->candidateUrl(['hours_mode' => 'custom']))->assertUnprocessable();
    }

    public function test_foreign_references_and_nonhired_members_are_refused_without_writes(): void
    {
        $foreignEvent = $this->event('Other');
        $foreignShift = $this->shift($foreignEvent);
        $foreignMember = $this->member('Foreign', null, 'hired', $foreignEvent);
        $nonhired = $this->member('Applied', null, 'applied');
        foreach ([$foreignMember, $nonhired] as $member) {
            $this->post($this->storeUrl(), $this->payload($member))->assertSessionHasErrors('team_engagement_id');
        }
        $member = $this->member('Alpha');
        $this->post($this->storeUrl(), $this->payload($member, ['shift_role_slot_id' => $foreignShift->roleSlots()->sole()->id]))->assertSessionHasErrors('shift_role_slot_id');
        $this->post(route('team.shifts.assignments.store', [$foreignEvent, $this->shift]), $this->payload($member))->assertNotFound();
        $this->post(route('team.shifts.assignments.store', [$foreignEvent, $foreignShift]), $this->payload($foreignMember))->assertNotFound();
        $this->getJson(route('team.shifts.assignment-candidates', $foreignShift))->assertNotFound();
        $this->getJson($this->candidateUrl(['shift_role_slot_id' => $foreignShift->roleSlots()->sole()->id]))->assertUnprocessable();
        $this->assertDatabaseCount('shift_assignments', 0);
    }

    public function test_duplicate_requests_and_database_constraint_keep_one_assignment(): void
    {
        $member = $this->member('Alpha');
        $assignment = $this->assign($member);
        $this->post($this->storeUrl(), $this->payload($member))->assertSessionHasErrors('team_engagement_id');
        try {
            DB::transaction(fn () => $this->shift->assignments()->create($assignment->only(['team_engagement_id', 'shift_role_slot_id', 'role_id', 'starts_at', 'ends_at'])));
            $this->fail('The unique constraint must reject a duplicate.');
        } catch (QueryException) {
            $this->assertDatabaseCount('shift_assignments', 1);
        }
    }

    public function test_service_rechecks_stale_eligibility_and_hours(): void
    {
        $member = $this->member('Alpha');
        $data = $this->payload($member);
        $member->update(['status' => 'declined']);
        $this->assertServiceValidation(fn () => app(ShiftAssignmentService::class)->create($this->shift, $data), 'team_engagement_id');
        $member->update(['status' => 'hired']);
        $this->shift->update(['starts_at' => '2026-10-01T11:00']);
        $this->assertServiceValidation(fn () => app(ShiftAssignmentService::class)->create($this->shift, [...$data, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T12:00']), 'ends_at');
        $this->shift->roleSlots()->delete();
        $this->assertServiceValidation(fn () => app(ShiftAssignmentService::class)->create($this->shift, $data), 'shift_role_slot_id');
        $this->assertDatabaseCount('shift_assignments', 0);
    }

    public function test_overlaps_use_assignment_hours_warn_on_both_rosters_and_allow_saving(): void
    {
        $member = $this->member('Alpha');
        $first = $this->assign($member, ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:30']);
        $other = $this->shift($this->event, 'Other shift');
        $data = $this->payload($member, ['shift_role_slot_id' => $other->roleSlots()->sole()->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T13:00']);
        $this->getJson(route('team.shifts.assignment-candidates', $other).'?'.http_build_query(array_diff_key($data, ['team_engagement_id' => true])))
            ->assertOk()->assertJsonPath('data.0.overlaps.0.overlap_minutes', 30);
        $this->post(route('team.shifts.assignments.store', [$this->event, $other]), $data)->assertSessionHasNoErrors();
        foreach ([$this->shift, $other] as $shift) {
            $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
                ->where('shift.assignments.0.overlaps.0.overlap_minutes', 30));
        }
        $first->update(['ends_at' => '2026-10-01T12:00']);
        $this->get(route('team.shifts.show', $other))->assertInertia(fn (Assert $page) => $page->has('shift.assignments.0.overlaps', 0));
        $this->getJson($this->candidateUrl(['hours_mode' => 'custom', 'starts_at' => '2026-10-01T13:00', 'ends_at' => '2026-10-01T14:00']))
            ->assertJsonPath('data.0.overlaps', []);
    }

    public function test_overlap_minutes_use_elapsed_time_across_daylight_saving_changes(): void
    {
        $this->shift->update(['starts_at' => '2026-11-01T00:00', 'ends_at' => '2026-11-01T04:00']);
        $member = $this->member('Alpha');
        $this->assign($member);
        $other = $this->shift($this->event, 'DST shift');
        $other->update(['starts_at' => '2026-11-01T00:00', 'ends_at' => '2026-11-01T04:00']);
        app(ShiftAssignmentService::class)->create($other, $this->payload($member, ['shift_role_slot_id' => $other->roleSlots()->sole()->id]));
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.overlaps.0.overlap_minutes', 300));
    }

    public function test_later_team_changes_preserve_roster_and_saved_position_role(): void
    {
        $member = $this->member('Alpha', $this->role);
        $assignment = $this->assign($member);
        $member->update(['status' => 'declined', 'role_id' => null]);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.name', 'Alpha')->where('shift.assignments.0.role_name', 'Gate crew')->where('shift.filled_count', 1));
        $this->getJson($this->candidateUrl())->assertJsonPath('meta.total', 0);
        $this->assertSame($this->role->id, $assignment->fresh()->role_id);
        $this->assertServiceValidation(fn () => $member->delete(), 'team_engagement_id');
        $this->assertModelExists($member);
    }

    public function test_view_only_users_receive_roster_but_no_candidates_or_write_access(): void
    {
        $assignment = $this->assign($this->member('Alpha'));
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)->where('shift.assignments.0.name', 'Alpha')->missing('candidates'));
        $this->getJson($this->candidateUrl())->assertForbidden()->assertJsonMissingPath('data');
        $this->post($this->storeUrl(), $this->payload($this->member('Beta')))->assertForbidden();
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $assignment]))->assertForbidden();
        $this->grantRoleAccess($this->user, ['team.edit']);
        $this->get(route('team.shifts.show', $this->shift))->assertForbidden();
        $this->getJson($this->candidateUrl())->assertForbidden();
    }

    public function test_scheduling_edit_needs_no_team_permission_and_writes_in_nonprimary_current_event(): void
    {
        $other = $this->event('Current');
        $shift = $this->shift($other);
        $this->grantRoleAccess($this->user, ['scheduling.edit']);
        $this->user->setCurrentEvent($other);
        $member = $this->member('Other member', null, 'hired', $other);
        $this->post(route('team.shifts.assignments.store', [$other, $shift]), $this->payload($member, ['shift_role_slot_id' => $shift->roleSlots()->sole()->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->event->id, app(OrganizationContext::class)->organization()->active_event_id);
    }

    public function test_locked_event_blocks_admin_and_editor_assignment_writes_including_services(): void
    {
        $member = $this->member('Alpha');
        $assignment = $this->assign($member);
        $this->event->lock();
        foreach ([true, false] as $admin) {
            if (! $admin) {
                $this->grantRoleAccess($this->user, ['scheduling.edit']);
            }
            $this->post($this->storeUrl(), $this->payload($this->member('New '.($admin ? 'admin' : 'editor'))))->assertForbidden();
            $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $assignment]))->assertForbidden();
        }
        try {
            app(ShiftAssignmentService::class)->delete($this->shift, $assignment);
            $this->fail('Service must recheck locks.');
        } catch (HttpException) {
            $this->assertModelExists($assignment);
        }
    }

    public function test_foreign_assignment_removal_and_stale_delete_confirmation_are_refused(): void
    {
        $member = $this->member('Alpha');
        $assignment = $this->assign($member);
        $other = $this->shift($this->event);
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $other, $assignment]))->assertNotFound();
        $this->delete(route('team.shifts.destroy', [$this->event, $this->shift]), ['assignment_count' => 0])->assertSessionHasErrors('assignment_count');
        $this->assertModelExists($assignment);
        $this->assertServiceValidation(fn () => app(ShiftService::class)->delete($this->shift, 0), 'assignment_count');
        $this->delete(route('team.shifts.destroy', [$this->event, $this->shift]), ['assignment_count' => 1])->assertSessionHasNoErrors();
        $this->assertModelMissing($assignment);
        $this->assertModelExists($member);
    }

    public function test_event_deletion_orders_children_and_preserves_unrelated_people_roles_and_assignments(): void
    {
        $member = $this->member('Alpha');
        $assignment = $this->assign($member);
        $otherEvent = $this->event('Other');
        $otherShift = $this->shift($otherEvent);
        $otherMember = $this->member('Other', null, 'hired', $otherEvent);
        $otherAssignment = app(ShiftAssignmentService::class)->create($otherShift, $this->payload($otherMember, ['shift_role_slot_id' => $otherShift->roleSlots()->sole()->id]));
        app(EventService::class)->delete($this->event);
        $this->assertModelMissing($assignment);
        $this->assertModelMissing($member);
        $this->assertModelExists($member->person);
        $this->assertModelExists($this->role);
        $this->assertModelExists($otherAssignment);
    }

    public function test_interval_and_restrict_constraints_are_enforced_in_the_database(): void
    {
        $member = $this->member('Alpha');
        $assignment = $this->assign($member);
        foreach ([fn () => DB::table('team_engagements')->where('id', $member->id)->delete(),
            fn () => $assignment->update(['ends_at' => '2026-10-01T09:00'])] as $write) {
            try {
                DB::transaction($write);
                $this->fail('Database constraint should reject this write.');
            } catch (QueryException) {
                $this->assertModelExists($assignment);
            }
        }
    }

    public function test_candidate_and_list_queries_stay_bounded_without_loading_list_people(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->assign($this->member('Member '.$i));
        }
        DB::enableQueryLog();
        $this->getJson($this->candidateUrl(['per_page' => 5]))->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 30);
        $candidateQueries = DB::getQueryLog();
        DB::flushQueryLog();
        $rows = app(ShiftRepository::class)->dataTable($this->event, '', 2, 'asc', 0, 25)['rows'];
        $this->assertSame(30, $rows->first()->assignments_count);
        $this->assertFalse($rows->first()->relationLoaded('assignments'));
        $listQueries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(25, count($candidateQueries));
        $this->assertLessThan(25, count($listQueries));
        $this->assertFalse(collect($listQueries)->contains(fn ($query) => str_contains($query['query'], '"people"')));
    }

    public function test_roster_actions_preserve_the_shift_pages_return_context(): void
    {
        foreach (['schedule', 'list'] as $tab) {
            $context = ['return_tab' => $tab, 'schedule_date' => '2026-10-02'];
            $returnUrl = route('team.shifts.show', ['shift' => $this->shift, ...$context]);
            $this->post($this->storeUrl(), $this->payload($this->member('Member '.$tab), $context))->assertRedirect($returnUrl);
            $assignment = $this->shift->assignments()->firstOrFail();
            $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $assignment]), $context)->assertRedirect($returnUrl);
            $this->assertModelMissing($assignment);
        }
    }

    private function assertServiceValidation(callable $write, string $key): void
    {
        try {
            $write();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02', 'timezone' => 'America/Vancouver']);
    }

    private function shift(Event $event, string $name = 'Show run'): Shift
    {
        $location = $event->locations()->create(['name' => 'Gate']);

        return app(ShiftService::class)->create($event, ['name' => $name, 'location_id' => $location->id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', 'slots' => [['role_id' => $this->role->id, 'needed' => 2]]]);
    }

    private function member(string $name, ?Role $role = null, string $status = 'hired', ?Event $event = null): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => fake()->unique()->safeEmail(), 'phone' => '555-555-5555']);

        return TeamEngagement::create(['event_id' => ($event ?? $this->event)->id, 'person_id' => $person->id, 'role_id' => $role?->id, 'status' => $status, 'employment_type' => 'paid', 'hourly_pay' => '20.00']);
    }

    private function payload(TeamEngagement $member, array $overrides = []): array
    {
        return [...['shift_role_slot_id' => $this->slotId, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'], ...$overrides];
    }

    private function assign(TeamEngagement $member, array $overrides = []): ShiftAssignment
    {
        return app(ShiftAssignmentService::class)->create($this->shift, $this->payload($member, $overrides));
    }

    private function storeUrl(): string
    {
        return route('team.shifts.assignments.store', [$this->event, $this->shift]);
    }

    private function updateUrl(): string
    {
        return route('team.shifts.update', [$this->event, $this->shift]);
    }

    private function candidateUrl(array $overrides = []): string
    {
        return route('team.shifts.assignment-candidates', $this->shift).'?'.http_build_query(['shift_role_slot_id' => $this->slotId, 'hours_mode' => 'full_shift', ...$overrides]);
    }

    private function shiftPayload(array $overrides = []): array
    {
        return ['name' => 'Show run', 'location_id' => $this->shift->location_id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', ...$overrides];
    }
}
