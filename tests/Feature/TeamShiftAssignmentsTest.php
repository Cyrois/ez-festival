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
use Illuminate\Testing\TestResponse;
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

    public function test_headcount_role_is_a_suggestion_and_different_role_members_fill_the_position_without_role_changes(): void
    {
        $otherRole = Role::create(['name' => 'First aid']);
        $member = $this->member('Medic', $otherRole);
        $this->getJson($this->candidateUrl(['search' => 'Medic']))
            ->assertJsonPath('data.0.suggested', false)->assertJsonPath('data.0.role_name', 'First aid');
        $this->post($this->storeUrl(), $this->payload($member))->assertSessionHasNoErrors();
        $this->assertSame($otherRole->id, $member->fresh()->role_id);
        $assignment = $this->shift->assignments()->sole();
        $this->assertSame($this->slotId, $assignment->shift_role_slot_id);
        $this->assertSame($this->role->id, $assignment->role_id);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 1)->where('shift.extra_count', 0)
            ->where('shift.assignments.0.role_name', 'Gate crew'));
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

    public function test_quantity_cannot_be_reduced_below_remaining_assignments(): void
    {
        $first = $this->assign($this->member('Alpha'));
        $second = $this->assign($this->member('Beta'));
        $slots = [['id' => $this->slotId, 'role_id' => $this->role->id, 'needed' => 1]];
        $data = $this->shiftPayload(['slots' => $slots]);
        $this->put($this->updateUrl(), $data)->assertSessionHasErrors('slots.0.needed');
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($this->shift, $data), 'slots.0.needed');
        $this->assertSame(2, $this->shift->roleSlots()->sole()->needed);
        $this->assertModelExists($first);
        $this->assertModelExists($second);
        $this->put($this->updateUrl(), [...$data, 'assignment_removals' => [$second->id]])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->shift->roleSlots()->sole()->needed);
        $this->assertModelExists($first);
        $this->assertModelMissing($second);
    }

    public function test_assigned_requirements_cannot_be_removed_or_changed_until_people_are_removed(): void
    {
        $assignment = $this->assign($this->member('Alpha'));
        $newRole = Role::create(['name' => 'Sound']);
        $change = $this->shiftPayload(['slots' => [['id' => $this->slotId, 'role_id' => $newRole->id, 'needed' => 1]]]);
        $this->put($this->updateUrl(), $change)->assertSessionHasErrors('slots.0.role_id');
        $remove = $this->shiftPayload(['slots' => []]);
        $this->put($this->updateUrl(), $remove)->assertSessionHasErrors('slots');
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($this->shift, $remove), 'slots');
        $this->assertSame($this->slotId, $assignment->fresh()->shift_role_slot_id);
        $this->put($this->updateUrl(), [...$remove, 'assignment_removals' => [$assignment->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($assignment);
        $this->assertSame(0, $this->shift->roleSlots()->count());
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
        $this->getJson($this->candidateUrl())->assertJsonPath('meta.per_page', 25);
        $this->assign($matching);
        $this->getJson($this->candidateUrl(['search' => 'zULu']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.on_shift', true);
        $this->getJson($this->candidateUrl(['per_page' => 26]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($this->candidateUrl(['search' => '%']))->assertJsonPath('meta.total', 0);
    }

    public function test_candidates_rank_availability_before_roles_across_pages_and_refresh_for_custom_hours(): void
    {
        $freeRole = $this->member('Zulu free crew', $this->role);
        $freeOther = $this->member('Alpha free other');
        $busyRole = $this->member('Zulu busy crew', $this->role);
        $busyOther = $this->member('Alpha busy other');
        $onRole = $this->member('Zulu on crew', $this->role);
        $onOther = $this->member('Alpha on other');
        $this->assign($onOther);
        $this->assign($onRole);
        $later = $this->shift($this->event, 'Later');
        foreach ([$busyOther, $busyRole, $onRole] as $member) {
            app(ShiftAssignmentService::class)->create($later, $this->payload($member, [
                'shift_role_slot_id' => $later->roleSlots()->sole()->id,
                'hours_mode' => 'custom', 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T13:00',
            ]));
        }
        $expected = [$freeRole, $freeOther, $busyRole, $busyOther, $onRole, $onOther];
        foreach ($expected as $index => $member) {
            $this->getJson($this->candidateUrl(['per_page' => 1, 'page' => $index + 1]))
                ->assertOk()->assertJsonPath('meta.total', 6)->assertJsonPath('data.0.id', $member->id);
        }
        $this->getJson($this->candidateUrl(['hours_mode' => 'custom', 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T12:00']))
            ->assertOk()->assertJsonPath('data.0.id', $busyRole->id)->assertJsonPath('data.0.overlaps', [])
            ->assertJsonPath('data.1.id', $freeRole->id)->assertJsonPath('data.2.id', $busyOther->id);
    }

    public function test_role_filter_and_selected_lookup_stay_scoped_to_hired_event_members_and_search(): void
    {
        $crew = $this->member('Crew', $this->role);
        $other = $this->member('Other', Role::create(['name' => 'Sound']));
        $this->member('Declined', $this->role, 'declined');
        $foreign = $this->member('Foreign', $this->role, 'hired', $this->event('Other event'));
        $this->getJson($this->candidateUrl(['role_filter' => 'has_role']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $crew->id);
        $this->getJson($this->candidateUrl(['role_filter' => 'everyone']))->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson($this->candidateUrl(['role_filter' => 'has_role', 'search' => 'other']))->assertJsonCount(0, 'data');
        foreach ([['selected_id' => $foreign->id], ['selected_id' => $other->id, 'role_filter' => 'has_role'],
            ['selected_id' => $crew->id, 'search' => 'other']] as $filter) {
            $this->getJson($this->candidateUrl($filter))->assertOk()->assertJsonCount(0, 'data');
        }
        $this->getJson($this->candidateUrl(['selected_id' => $crew->id]))->assertOk()->assertJsonPath('data.0.id', $crew->id);
        $this->getJson($this->candidateUrl(['role_filter' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('role_filter');
        $this->getJson($this->candidateUrl(['selected_id' => 0]))->assertUnprocessable()->assertJsonValidationErrors('selected_id');
    }

    public function test_default_candidate_page_contains_25_people_and_caps_larger_requests(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->member(sprintf('Person %02d', $i), $this->role);
        }
        $this->getJson($this->candidateUrl())->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)->assertJsonPath('meta.last_page', 2);
        $this->getJson($this->candidateUrl(['page' => 2]))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->candidateUrl(['per_page' => 26]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
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

    public function test_other_shifts_include_names_colors_and_assignment_times_in_the_visible_window_without_foreign_event_data(): void
    {
        $member = $this->member('Context crew');
        $assignment = $this->assign($member, ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00']);
        $others = collect();
        foreach ([
            ['Before', 'violet', '09:35', '09:50'],
            ['Overlap', 'teal', '11:30', '12:30'],
            ['Later', 'danger', '13:00', '13:30'],
            ['Outside', 'warning', '14:30', '15:00'],
        ] as [$name, $color, $start, $end]) {
            $other = $this->shift($this->event, $name);
            $other->update(['color' => $color, 'starts_at' => "2026-10-01T$start", 'ends_at' => "2026-10-01T$end"]);
            app(ShiftAssignmentService::class)->create($other, $this->payload($member, ['shift_role_slot_id' => $other->roleSlots()->sole()->id]));
            $others->push($other);
        }
        $foreignEvent = $this->event('Foreign');
        $foreignMember = $this->member('Foreign crew', event: $foreignEvent);
        $foreign = $this->shift($foreignEvent, 'Foreign shift');
        app(ShiftAssignmentService::class)->create($foreign, $this->payload($foreignMember, ['shift_role_slot_id' => $foreign->roleSlots()->sole()->id]));
        $expected = $others->take(3)->map(fn ($other) => [
            'shift_id' => $other->id, 'shift_name' => $other->name, 'color' => $other->color,
            'starts_at' => $other->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $other->ends_at->format('Y-m-d\TH:i'),
        ])->all();
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.other_shifts', $expected)->where('shift.assignments.0.overlaps.0.overlap_minutes', 30));
        $this->getJson($this->candidateUrl(['search' => 'Context crew', 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00']))
            ->assertJsonPath('data.0.other_shifts', $expected);
        $preview = route('team.shifts.assignments.overlaps', [$this->shift, $assignment]);
        $this->getJson($preview.'?hours_mode=custom&starts_at=2026-10-01T10:00&ends_at=2026-10-01T11:00')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('other_shifts', $expected);
        $this->getJson($preview.'?'.http_build_query(['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00',
            'shift_starts_at' => '2026-10-01T10:00', 'shift_ends_at' => '2026-10-01T16:00']))
            ->assertOk()->assertJsonCount(4, 'other_shifts');
        $this->assertSame('2026-10-01T14:00', $this->shift->fresh()->ends_at->format('Y-m-d\TH:i'));
        $this->assertSame('2026-10-01T12:00', $assignment->fresh()->ends_at->format('Y-m-d\TH:i'));
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
            $this->getJson($this->candidateUrl(['role_filter' => 'has_role']))->assertForbidden();
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

    public function test_edit_hours_preserves_identity_counts_and_team_data_and_supports_full_shift(): void
    {
        $member = $this->member('Edit hours');
        $assignment = $this->assign($member);
        $identity = $assignment->only(['id', 'shift_id', 'team_engagement_id', 'shift_role_slot_id', 'role_id']);
        $team = $member->fresh()->toArray();
        $this->saveHours($assignment, ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:15', 'ends_at' => '2026-10-01T13:45', 'return_tab' => 'schedule', 'schedule_date' => '2026-10-01'])
            ->assertSessionHasNoErrors()->assertRedirect(route('team.scheduling', ['tab' => 'schedule', 'date' => '2026-10-01']));
        $this->assertSame($identity, $assignment->fresh()->only(array_keys($identity)));
        $this->assertSame($team, $member->fresh()->toArray());
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 1)->where('shift.extra_count', 0)->where('shift.slots.0.open_count', 1)
            ->where('shift.assignments.0.starts_at', '2026-10-01T11:15'));
        $this->saveHours($assignment, ['hours_mode' => 'full_shift'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01 10:00:00', $assignment->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 14:00:00', $assignment->fresh()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_edit_hours_validates_strict_payloads_without_writing(): void
    {
        $assignment = $this->assign($this->member('Invalid hours'));
        $before = $assignment->fresh()->toArray();
        $valid = ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T13:00'];
        foreach ([
            [], ['hours_mode' => 'unknown'], ['hours_mode' => 'custom'],
            [...$valid, 'starts_at' => 'invalid'], [...$valid, 'starts_at' => '2026-10-01T09:00'],
            [...$valid, 'ends_at' => '2026-10-01T15:00'], [...$valid, 'ends_at' => $valid['starts_at']],
            [...$valid, 'ends_at' => '2026-10-01T10:30'], [...$valid, 'hours_mode' => 'full_shift'],
            [...$valid, 'role_id' => $this->role->id], [...$valid, 'team_engagement_id' => $assignment->team_engagement_id],
            [...$valid, 'shift_role_slot_id' => $this->slotId], [...$valid, 'shift_id' => $this->shift->id],
        ] as $payload) {
            $this->saveHours($assignment, $payload)->assertSessionHasErrors();
            $this->assertSame($before, $assignment->fresh()->toArray());
        }
    }

    public function test_preview_refuses_foreign_and_missing_assignments(): void
    {
        $assignment = $this->assign($this->member('Scoped'));
        foreach ([$this->shift($this->event), $this->shift($this->event('Foreign'))] as $shift) {
            $this->getJson(route('team.shifts.assignments.overlaps', [$shift, $assignment]).'?hours_mode=full_shift')->assertNotFound();
        }
        $preview = route('team.shifts.assignments.overlaps', [$this->shift, $assignment]);
        $assignment->delete();
        $this->getJson($preview.'?hours_mode=full_shift')->assertNotFound();
    }

    public function test_edit_hours_permissions_and_locks_are_enforced_in_http_and_service(): void
    {
        $assignment = $this->assign($this->member('Permissions'));
        $preview = route('team.shifts.assignments.overlaps', [$this->shift, $assignment]).'?hours_mode=full_shift';
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->saveHours($assignment, ['hours_mode' => 'full_shift'])->assertForbidden();
        $this->getJson($preview)->assertForbidden()->assertJsonMissingPath('data');
        $this->grantRoleAccess($this->user, ['scheduling.edit']);
        $this->saveHours($assignment, ['hours_mode' => 'full_shift'])->assertSessionHasNoErrors();
        $this->getJson($preview)->assertOk();
        $this->event->lock();
        foreach ([false, true] as $admin) {
            if ($admin) {
                $this->grantAdminAccess($this->user);
            }
            $this->saveHours($assignment, ['hours_mode' => 'full_shift'])->assertForbidden();
        }
        try {
            app(ShiftService::class)->update($this->shift, $this->shiftPayload(['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'full_shift']]]));
            $this->fail('Service must reject locked events.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertModelExists($assignment);
        }
    }

    public function test_edit_hours_supports_detached_assignments_changed_members_and_overnight_hours(): void
    {
        $member = $this->member('Detached');
        $assignment = $this->assign($member);
        $this->shift->roleSlots()->delete();
        $member->update(['status' => 'declined', 'role_id' => null]);
        $this->shift->update(['starts_at' => '2026-10-01T21:00', 'ends_at' => '2026-10-02T02:00']);
        $this->saveHours($assignment, [
            'hours_mode' => 'custom', 'starts_at' => '2026-10-01T23:15', 'ends_at' => '2026-10-02T01:45',
        ])->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.shift_role_slot_id', null)->where('shift.assignments.0.is_extra', true)
            ->where('shift.assignments.0.role_name', 'Gate crew')->where('shift.assignments.0.ends_at', '2026-10-02T01:45'));
    }

    public function test_preview_discovers_new_conflicts_without_writing_and_page_save_applies_hours(): void
    {
        $member = $this->member('Conflict');
        $assignment = $this->assign($member, ['hours_mode' => 'custom', 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T11:00']);
        $other = $this->shift($this->event, 'Later');
        app(ShiftAssignmentService::class)->create($other, ['shift_role_slot_id' => $other->roleSlots()->sole()->id, 'team_engagement_id' => $member->id,
            'hours_mode' => 'custom', 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T13:00']);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page->has('shift.assignments.0.overlaps', 0));
        $preview = route('team.shifts.assignments.overlaps', [$this->shift, $assignment]);
        $this->getJson($preview.'?hours_mode=full_shift')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shift_name', 'Later')->assertJsonPath('data.0.overlap_minutes', 60)
            ->assertJsonMissingPath('data.0.team_engagement_id')->assertJsonMissingPath('data.0.email');
        $this->getJson($preview.'?'.http_build_query(['hours_mode' => 'custom', 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T12:00']))
            ->assertOk()->assertJsonPath('data', []);
        $this->getJson($preview.'?hours_mode=custom')->assertUnprocessable();
        $this->getJson($preview.'?hours_mode=full_shift&role_id=1')->assertUnprocessable();
        $this->saveHours($assignment, ['hours_mode' => 'full_shift'])->assertSessionHasNoErrors();
        $this->assertSame('14:00', $assignment->fresh()->ends_at->format('H:i'));
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02', 'timezone' => 'America/Vancouver']);
    }

    public function test_page_save_applies_roster_changes_with_details_and_preserves_identity(): void
    {
        $first = $this->assign($this->member('First'));
        $removed = $this->assign($this->member('Removed'));
        $member = $this->member('New person', Role::create(['name' => 'Different role']));
        $this->put($this->updateUrl(), $this->shiftPayload([
            'name' => 'Changed shift',
            'starts_at' => '2026-10-01T11:00',
            'assignment_updates' => [['id' => $first->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:15', 'ends_at' => '2026-10-01T13:45']],
            'assignment_removals' => [$removed->id],
            'assignment_additions' => [$this->payload($member)],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Changed shift', $this->shift->fresh()->name);
        $this->assertModelMissing($removed);
        $this->assertSame('11:15', $first->fresh()->starts_at->format('H:i'));
        $this->assertSame($this->role->id, $first->fresh()->role_id);
        $new = $this->shift->assignments()->where('team_engagement_id', $member->id)->sole();
        $this->assertSame('11:00', $new->starts_at->format('H:i'));
        $this->assertSame($this->slotId, $new->shift_role_slot_id);
        $this->assertNotSame($this->role->id, $member->fresh()->role_id);
    }

    public function test_invalid_roster_drafts_fail_form_request_without_partial_writes(): void
    {
        $assignment = $this->assign($this->member('Original'));
        $otherShift = $this->shift($this->event, 'Other');
        $foreign = app(ShiftAssignmentService::class)->create($otherShift, $this->payload($this->member('Foreign'), ['shift_role_slot_id' => $otherShift->roleSlots()->sole()->id]));
        $invalid = [
            ['assignment_updates' => [['id' => $foreign->id, 'hours_mode' => 'full_shift']]],
            ['assignment_removals' => [$foreign->id]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T11:00']]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'full_shift', 'role_id' => $this->role->id]]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'custom']]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'full_shift', 'starts_at' => '2026-10-01T10:00']]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'full_shift']], 'assignment_removals' => [$assignment->id]],
            ['assignment_removals' => [$assignment->id, $assignment->id]],
        ];
        foreach ($invalid as $changes) {
            $this->put($this->updateUrl(), $this->shiftPayload(['name' => 'Must not save', ...$changes]))->assertSessionHasErrors();
            $this->assertModelExists($assignment);
            $this->assertSame('10:00', $assignment->fresh()->starts_at->format('H:i'));
            $this->assertNotSame('Must not save', $this->shift->fresh()->name);
        }
    }

    public function test_roster_creation_conflict_rolls_back_details_removals_and_hours(): void
    {
        $member = $this->member('Already there');
        $assignment = $this->assign($member);
        $removed = $this->assign($this->member('Keep until save succeeds'));
        $this->put($this->updateUrl(), $this->shiftPayload([
            'name' => 'Must not save',
            'assignment_removals' => [$removed->id],
            'assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00']],
            'assignment_additions' => [$this->payload($member)],
        ]))->assertSessionHasErrors('assignment_additions.0.team_engagement_id');
        $this->assertModelExists($removed);
        $this->assertSame('10:00', $assignment->fresh()->starts_at->format('H:i'));
        $this->assertNotSame('Must not save', $this->shift->fresh()->name);
    }

    public function test_roster_save_rejects_locked_event_and_foreign_member_or_slot(): void
    {
        $assignment = $this->assign($this->member('Original'));
        $other = $this->event('Other');
        $foreign = $this->member('Foreign', event: $other);
        $foreignShift = $this->shift($other, 'Foreign shift');
        $this->put($this->updateUrl(), $this->shiftPayload(['assignment_additions' => [$this->payload($foreign)]]))->assertSessionHasErrors('assignment_additions.0.team_engagement_id');
        $this->put($this->updateUrl(), $this->shiftPayload(['assignment_additions' => [$this->payload($this->member('Local'), ['shift_role_slot_id' => $foreignShift->roleSlots()->sole()->id])]]))->assertSessionHasErrors('assignment_additions.0.shift_role_slot_id');
        $this->put(route('team.shifts.update', [$other, $this->shift]), $this->shiftPayload(['assignment_removals' => [$assignment->id]]))->assertNotFound();
        $this->event->lock();
        $changes = $this->shiftPayload(['assignment_removals' => [$assignment->id]]);
        $this->put($this->updateUrl(), $changes)->assertForbidden();
        $this->expectException(HttpException::class);
        app(ShiftService::class)->update($this->shift, $changes);
    }

    public function test_roster_service_rechecks_stale_ids_and_shift_containment_before_writes(): void
    {
        $assignment = $this->assign($this->member('Original'));
        $id = $assignment->id;
        $assignment->delete();
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($this->shift, $this->shiftPayload(['assignment_removals' => [$id]])), 'assignment_removals.0');
        $kept = $this->assign($this->member('Keep'));
        $this->put($this->updateUrl(), $this->shiftPayload(['starts_at' => '2026-10-01T11:00']))->assertSessionHasErrors('ends_at');
        $this->assertSame('10:00', $kept->fresh()->starts_at->format('H:i'));
    }

    public function test_draft_overlap_preview_accepts_unsaved_bounds_and_scopes_new_members(): void
    {
        $member = $this->member('Draft');
        $other = $this->shift($this->event, 'Other');
        app(ShiftAssignmentService::class)->create($other, $this->payload($member, ['shift_role_slot_id' => $other->roleSlots()->sole()->id]));
        $url = route('team.shifts.assignment-overlaps', $this->shift).'?'.http_build_query([
            'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift',
            'shift_starts_at' => '2026-10-01T09:00', 'shift_ends_at' => '2026-10-01T11:00',
        ]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.overlap_minutes', 60)->assertJsonMissingPath('data.0.team_engagement_id');
        $foreign = $this->member('Foreign', event: $this->event('Other event'));
        $this->getJson(route('team.shifts.assignment-overlaps', $this->shift).'?hours_mode=full_shift&team_engagement_id='.$foreign->id)->assertUnprocessable();
        $this->assertSame(0, $this->shift->assignments()->count());
    }

    public function test_new_headcount_rows_and_their_assignments_save_together_without_changing_team_roles(): void
    {
        $different = Role::create(['name' => 'Sound']);
        $member = $this->member('First draft', $different);
        $second = $this->member('Second draft');
        $saved = ['id' => $this->slotId, 'role_id' => $this->role->id, 'needed' => 2];
        $this->put($this->updateUrl(), $this->shiftPayload([
            'color' => 'warning',
            'slots' => [$saved,
                ['id' => null, 'client_key' => 'draft-1', 'role_id' => $this->role->id, 'needed' => 1],
                ['id' => null, 'client_key' => 'draft-2', 'role_id' => $this->role->id, 'needed' => 2],
            ],
            'assignment_additions' => [
                ['slot_key' => 'draft-1', 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'],
                ['slot_key' => 'draft-2', 'team_engagement_id' => $second->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00'],
            ],
        ]))->assertSessionHasNoErrors();
        $slots = $this->shift->roleSlots()->orderBy('sort_order')->get();
        $this->assertCount(3, $slots);
        $this->assertSame($slots[1]->id, $this->shift->assignments()->where('team_engagement_id', $member->id)->sole()->shift_role_slot_id);
        $this->assertSame($slots[2]->id, $this->shift->assignments()->where('team_engagement_id', $second->id)->sole()->shift_role_slot_id);
        $this->assertSame($different->id, $member->fresh()->role_id);
        $this->assertSame('warning', $this->shift->fresh()->color);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.total_needs', 5)->where('shift.filled_count', 2)->where('shift.slots.2.open_count', 1));
    }

    public function test_invalid_draft_headcount_references_never_create_slots_or_assignments(): void
    {
        $member = $this->member('Draft');
        $base = ['id' => null, 'client_key' => 'draft-1', 'role_id' => $this->role->id, 'needed' => 1];
        $addition = ['slot_key' => 'draft-1', 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'];
        foreach ([
            ['slots' => [], 'assignment_additions' => [$addition]],
            ['slots' => [$base, $base], 'assignment_additions' => [$addition]],
            ['slots' => [$base], 'assignment_additions' => [[...$addition, 'shift_role_slot_id' => $this->slotId]]],
            ['slots' => [$base], 'assignment_additions' => [[...$addition, 'slot_key' => 'draft-999']]],
            ['slots' => [$base], 'assignment_additions' => [[...$addition, 'team_engagement_id' => $this->member('Foreign', event: $this->event('Other'))->id]]],
        ] as $data) {
            $this->put($this->updateUrl(), $this->shiftPayload([...$data, 'name' => 'Must not save']))->assertSessionHasErrors();
            $this->assertSame(1, $this->shift->roleSlots()->count());
            $this->assertSame(0, $this->shift->assignments()->count());
            $this->assertSame('Show run', $this->shift->fresh()->name);
        }
        $valid = $this->shiftPayload(['slots' => [$base], 'assignment_additions' => [$addition]]);
        $this->event->lock();
        $this->put($this->updateUrl(), $valid)->assertForbidden();
        try {
            app(ShiftService::class)->update($this->shift, $valid);
            $this->fail('Locked event must reject draft Headcount.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(1, $this->shift->roleSlots()->count());
        }
    }

    public function test_draft_slot_assignment_conflict_rolls_back_new_headcount_and_details(): void
    {
        $member = $this->member('Already assigned');
        $this->assign($member);
        $this->put($this->updateUrl(), $this->shiftPayload([
            'name' => 'Must not save',
            'slots' => [
                ['id' => $this->slotId, 'role_id' => $this->role->id, 'needed' => 2],
                ['id' => null, 'client_key' => 'draft-1', 'role_id' => $this->role->id, 'needed' => 1],
            ],
            'assignment_additions' => [['slot_key' => 'draft-1', 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift']],
        ]))->assertSessionHasErrors('assignment_additions.0.team_engagement_id');
        $this->assertSame(1, $this->shift->roleSlots()->count());
        $this->assertSame(1, $this->shift->assignments()->count());
        $this->assertSame('Show run', $this->shift->fresh()->name);
    }

    public function test_unsaved_role_candidates_are_scoped_paginated_and_read_only(): void
    {
        $member = $this->member('Suggested', $this->role);
        $url = route('team.shifts.assignment-candidates', $this->shift).'?hours_mode=full_shift&role_id='.$this->role->id;
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id', $member->id)->assertJsonPath('data.0.suggested', true);
        $this->assertSame(1, $this->shift->roleSlots()->count());
        $this->assertSame(0, $this->shift->assignments()->count());
        $this->getJson($url.'&shift_role_slot_id='.$this->slotId)->assertUnprocessable();
        $off = Role::create(['name' => 'Off', 'active' => false]);
        $this->getJson(route('team.shifts.assignment-candidates', $this->shift).'?hours_mode=full_shift&role_id='.$off->id)->assertUnprocessable();
        $foreign = $this->shift($this->event('Other'), 'Foreign');
        $this->getJson(route('team.shifts.assignment-candidates', $foreign).'?hours_mode=full_shift&role_id='.$this->role->id)->assertNotFound();
        $this->event->lock();
        $this->getJson($url)->assertForbidden();
    }

    public function test_create_shift_saves_details_headcount_breaks_and_complete_roster_together(): void
    {
        $different = Role::create(['name' => 'Sound']);
        $first = $this->member('Full shift', $different);
        $second = $this->member('Custom hours');
        $extra = $this->member('Extra person');
        $data = $this->createPayload([
            'breaks' => [['duration_minutes' => 15, 'starts_at' => '2026-10-01T11:00']],
            'assignment_additions' => [
                ['slot_key' => 'draft-1', 'team_engagement_id' => $first->id, 'hours_mode' => 'full_shift'],
                ['slot_key' => 'draft-2', 'team_engagement_id' => $second->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00'],
                ['slot_key' => 'draft-1', 'team_engagement_id' => $extra->id, 'hours_mode' => 'full_shift'],
            ],
        ]);
        $this->post(route('team.shifts.store', $this->event), $data)->assertSessionHasNoErrors();
        $created = $this->event->shifts()->where('name', 'New shift')->sole();
        $slots = $created->roleSlots()->get();
        $this->assertCount(2, $slots);
        $this->assertSame(3, $created->assignments()->count());
        $this->assertSame(1, $created->breaks()->count());
        $this->assertSame($slots[0]->id, $created->assignments()->where('team_engagement_id', $first->id)->sole()->shift_role_slot_id);
        $this->assertSame($slots[1]->id, $created->assignments()->where('team_engagement_id', $second->id)->sole()->shift_role_slot_id);
        $this->assertSame($different->id, $first->fresh()->role_id);
        $this->get(route('team.shifts.show', $created))->assertInertia(fn (Assert $page) => $page
            ->where('shift.filled_count', 2)->where('shift.total_needs', 2)->where('shift.extra_count', 1)
            ->where('shift.assignments.1.starts_at', '2026-10-01T11:00')
            ->where('shift.assignments.2.is_extra', true));
        $this->delete(route('team.shifts.destroy', [$this->event, $created]), ['assignment_count' => 3])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('shifts', ['id' => $created->id]);
        $this->assertDatabaseMissing('shift_assignments', ['shift_id' => $created->id]);
        $this->assertDatabaseMissing('shift_role_slots', ['shift_id' => $created->id]);
        $this->assertDatabaseMissing('shift_breaks', ['shift_id' => $created->id]);
    }

    public function test_create_shift_rejects_invalid_roster_drafts_without_partial_writes(): void
    {
        $member = $this->member('Draft');
        $addition = ['slot_key' => 'draft-1', 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'];
        $foreign = $this->member('Foreign', event: $this->event('Other'));
        $ineligible = $this->member('Not hired', status: 'applied');
        $assignment = $this->assign($member);
        foreach ([
            ['slots' => [], 'assignment_additions' => [$addition]],
            ['assignment_additions' => [[...$addition, 'slot_key' => 'draft-999']]],
            ['assignment_additions' => [[...$addition, 'team_engagement_id' => $foreign->id]]],
            ['assignment_additions' => [[...$addition, 'team_engagement_id' => $ineligible->id]]],
            ['assignment_additions' => [[...$addition, 'shift_role_slot_id' => $this->slotId]]],
            ['assignment_additions' => [$addition, $addition]],
            ['assignment_additions' => [[...$addition, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T11:00']]],
            ['assignment_additions' => [[...$addition, 'starts_at' => '2026-10-01T10:00']]],
            ['assignment_updates' => [['id' => $assignment->id, 'hours_mode' => 'full_shift']]],
            ['assignment_removals' => [$assignment->id]],
            ['name' => ''],
        ] as $invalid) {
            $this->post(route('team.shifts.store', $this->event), $this->createPayload($invalid))->assertSessionHasErrors();
            $this->assertSame(1, $this->event->shifts()->count());
            $this->assertSame(1, $this->shift->assignments()->count());
        }
        $this->post(route('team.shifts.store', $this->event('Wrong route')), $this->createPayload())->assertNotFound();
        $this->event->lock();
        $this->post(route('team.shifts.store', $this->event), $this->createPayload(['assignment_additions' => [$addition]]))->assertForbidden();
        try {
            app(ShiftService::class)->create($this->event, $this->createPayload());
            $this->fail('Locked event must reject shift creation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(1, $this->event->shifts()->count());
    }

    public function test_create_shift_roster_conflict_rolls_back_the_whole_transaction(): void
    {
        $member = $this->member('Duplicate');
        $addition = ['slot_key' => 'draft-1', 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'];
        $data = $this->createPayload(['assignment_additions' => [$addition, $addition]]);
        try {
            app(ShiftService::class)->create($this->event, $data);
            $this->fail('Duplicate roster must reject shift creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment_additions.1.team_engagement_id', $exception->errors());
        }
        $this->assertSame(1, $this->event->shifts()->count());
        $this->assertDatabaseCount('shift_role_slots', 1);
        $this->assertDatabaseCount('shift_assignments', 0);
    }

    public function test_create_roster_candidates_and_previews_are_scoped_paginated_and_read_only(): void
    {
        $member = $this->member('Suggested', $this->role);
        $this->assign($member);
        foreach (range(1, 6) as $index) {
            $this->member('Person '.$index);
        }
        $this->member('Not hired', status: 'applied');
        $foreignEvent = $this->event('Foreign');
        $foreign = $this->member('Foreign', event: $foreignEvent);
        $hours = ['hours_mode' => 'full_shift', 'shift_starts_at' => '2026-10-01T09:00', 'shift_ends_at' => '2026-10-01T11:00'];
        $url = route('team.shifts.create.assignment-candidates', $this->event).'?'.http_build_query([...$hours, 'role_id' => $this->role->id, 'per_page' => 5]);
        DB::enableQueryLog();
        $this->getJson($url)->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 7)
            ->assertJsonPath('data.0.on_shift', false)
            ->assertJsonPath('data.0.suggested', false)->assertJsonCount(0, 'data.0.overlaps')
            ->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.hourly_pay');
        $this->assertLessThan(25, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->getJson($url.'&page=2')->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $member->id)->assertJsonPath('data.1.on_shift', false)
            ->assertJsonPath('data.1.suggested', true)->assertJsonPath('data.1.overlaps.0.overlap_minutes', 60);
        $preview = route('team.shifts.create.assignment-overlaps', $this->event).'?'.http_build_query([...$hours, 'team_engagement_id' => $member->id]);
        $this->getJson($preview)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.overlap_minutes', 60)
            ->assertJsonPath('other_shifts.0.shift_id', $this->shift->id)->assertJsonMissingPath('data.0.team_engagement_id');
        $this->getJson($preview.'&hours_mode=custom&starts_at=2026-10-01T09:00&ends_at=2026-10-01T10:00')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('team.shifts.create.assignment-overlaps', $this->event).'?'.http_build_query([...$hours, 'team_engagement_id' => $foreign->id]))->assertUnprocessable();
        $this->getJson($url.'&shift_role_slot_id='.$this->slotId)->assertUnprocessable();
        $this->getJson(route('team.shifts.create.assignment-candidates', $this->event).'?hours_mode=full_shift&role_id='.$this->role->id)->assertUnprocessable();
        $this->getJson(route('team.shifts.create.assignment-candidates', $foreignEvent).'?'.http_build_query([...$hours, 'role_id' => $this->role->id]))->assertNotFound();
        $this->getJson($preview.'&unexpected=1')->assertUnprocessable();
        $this->assertSame(1, $this->event->shifts()->count());
        $this->assertSame(1, $this->shift->assignments()->count());
        $this->get(route('team.shifts.create'))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)->where('event.is_locked', false));
        $this->event->lock();
        $this->getJson($url)->assertForbidden();
        $this->getJson($preview)->assertForbidden();
        $this->event->unlock();
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->getJson($url)->assertForbidden();
        $this->getJson($preview)->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->createPayload())->assertForbidden();
    }

    public function test_override_candidates_and_assignments_work_without_any_roles_or_slots(): void
    {
        $this->shift->roleSlots()->delete();
        $this->role->delete();
        $member = $this->member('Role-free person');
        $before = $member->fresh()->toArray();
        $query = ['override' => true, 'hours_mode' => 'full_shift'];
        $this->getJson(route('team.shifts.assignment-candidates', $this->shift).'?'.http_build_query($query))
            ->assertOk()->assertJsonPath('data.0.id', $member->id)->assertJsonPath('data.0.suggested', false);
        $this->post($this->storeUrl(), $this->overridePayload($member))->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Role-free person assigned to shift.');
        $assignment = $this->shift->assignments()->sole();
        $this->assertNull($assignment->role_id);
        $this->assertNull($assignment->shift_role_slot_id);
        $this->assertSame($before, $member->fresh()->toArray());
        $this->assertDatabaseCount('roles', 0);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignment_count', 1)->where('shift.total_needs', 0)->where('shift.slots', [])
            ->where('shift.assignments.0.role_name', null)->where('shift.assignments.0.is_extra', true));
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.assignment_count', 1)
            ->assertJsonPath('data.0.total_needs', 0);
    }

    public function test_override_save_and_removal_change_assigned_count_without_consuming_role_vacancies(): void
    {
        $this->shift->roleSlots()->whereKey($this->slotId)->update(['needed' => 3]);
        $this->assign($this->member('First'));
        $second = $this->member('Second');
        $member = $this->member('Override', $this->role);
        $this->put($this->updateUrl(), $this->shiftPayload(['assignment_additions' => [$this->payload($second), $this->overridePayload($member)]]))
            ->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignment_count', 3)->where('shift.total_needs', 3)->where('shift.slots.0.open_count', 1));
        $assignment = $this->shift->assignments()->where('team_engagement_id', $member->id)->sole();
        $this->assertNull($assignment->role_id);
        $this->put($this->updateUrl(), $this->shiftPayload(['assignment_removals' => [$assignment->id]]))
            ->assertSessionHasNoErrors();
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.assignment_count', 2)
            ->assertJsonPath('data.0.total_needs', 3)->assertJsonPath('data.0.slots.0.open_count', 1);
    }

    public function test_override_candidate_order_and_pagination_have_no_role_preference(): void
    {
        $alpha = $this->member('Alpha');
        $zeta = $this->member('Zeta', $this->role);
        $overlap = $this->member('A overlapping');
        $already = $this->member('A assigned');
        $other = $this->shift($this->event, 'Other shift');
        app(ShiftAssignmentService::class)->create($other, $this->overridePayload($overlap));
        app(ShiftAssignmentService::class)->create($this->shift, $this->overridePayload($already));
        $this->member('Foreign', event: $this->event('Foreign event'));
        $this->member('Declined', status: 'declined');
        $url = route('team.shifts.assignment-candidates', $this->shift).'?'.http_build_query(['override' => true, 'hours_mode' => 'full_shift', 'per_page' => 2]);
        $this->getJson($url)->assertJsonPath('meta.total', 4)->assertJsonPath('data.0.id', $alpha->id)
            ->assertJsonPath('data.1.id', $zeta->id)->assertJsonPath('data.1.suggested', false);
        $this->getJson($url.'&page=2')->assertJsonPath('data.0.id', $overlap->id)
            ->assertJsonCount(1, 'data.0.overlaps')->assertJsonPath('data.1.id', $already->id)->assertJsonPath('data.1.on_shift', true);
        $this->getJson($url.'&role_filter=has_role')->assertUnprocessable();
        $this->post($this->storeUrl(), $this->overridePayload($overlap))->assertSessionHasNoErrors();
    }

    public function test_create_and_copy_preserve_role_free_override_hours_and_personal_breaks(): void
    {
        $member = $this->member('New override');
        $addition = [...$this->overridePayload($member), 'hours_mode' => 'custom',
            'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T13:00',
            'breaks' => [['duration_minutes' => 15, 'starts_at' => '2026-10-01T12:00']]];
        $this->post(route('team.shifts.store', $this->event), $this->createPayload(['slots' => [], 'assignment_additions' => [$addition]]))
            ->assertSessionHasNoErrors();
        $created = $this->event->shifts()->where('name', 'New shift')->sole();
        $this->assertSame(0, $created->roleSlots()->count());
        $assignment = $created->assignments()->sole();
        $this->assertNull($assignment->role_id);
        $this->assertSame(15, $assignment->breaks()->sole()->duration_minutes);
        $this->get(route('team.shifts.copy', $created))->assertInertia(fn (Assert $page) => $page
            ->where('prefill.assignments.0.role_name', null)->where('prefill.assignments.0.slot_index', null)
            ->where('prefill.assignments.0.breaks.0.duration_minutes', 15));
        $this->post(route('team.shifts.store', $this->event), $this->createPayload(['name' => 'Copied override', 'slots' => [], 'assignment_additions' => [$addition]]))
            ->assertSessionHasNoErrors();
        $copy = $this->event->shifts()->where('name', 'Copied override')->sole()->assignments()->sole();
        $this->assertNotSame($assignment->id, $copy->id);
        $this->assertNull($copy->role_id);
        $this->assertSame('11:00', $copy->starts_at->format('H:i'));
        $this->assertSame(15, $copy->breaks()->sole()->duration_minutes);
    }

    public function test_override_validation_and_duplicate_conflicts_roll_back_the_whole_save(): void
    {
        $member = $this->member('Override');
        $foreign = $this->member('Foreign', event: $this->event('Other'));
        $declined = $this->member('Declined', status: 'declined');
        foreach ([
            [...$this->overridePayload($foreign)],
            [...$this->overridePayload($declined)],
            [...$this->overridePayload($member), 'override' => false],
            [...$this->overridePayload($member), 'role_id' => $this->role->id],
            [...$this->overridePayload($member), 'shift_role_slot_id' => $this->slotId],
            [...$this->overridePayload($member), 'slot_key' => 'draft-1'],
            [...$this->overridePayload($member), 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T12:00'],
        ] as $addition) {
            $this->put($this->updateUrl(), $this->shiftPayload(['name' => 'Must roll back', 'assignment_additions' => [$addition]]))
                ->assertSessionHasErrors();
            $this->assertSame('Show run', $this->shift->fresh()->name);
            $this->assertSame(0, $this->shift->assignments()->count());
        }
        app(ShiftAssignmentService::class)->create($this->shift, $this->overridePayload($member));
        $this->put($this->updateUrl(), $this->shiftPayload(['name' => 'Duplicate rollback', 'assignment_additions' => [$this->overridePayload($member)]]))
            ->assertSessionHasErrors('assignment_additions.0.team_engagement_id');
        $this->post($this->storeUrl(), $this->overridePayload($member))->assertSessionHasErrors('team_engagement_id');
        $this->assertSame('Show run', $this->shift->fresh()->name);
        $this->assertSame(1, $this->shift->assignments()->count());
        $this->assertServiceValidation(fn () => app(ShiftAssignmentService::class)->create($this->shift, [...$this->overridePayload($member), 'role_id' => $this->role->id]), 'override');
    }

    public function test_overrides_keep_permission_lock_and_deletion_guards(): void
    {
        $member = $this->member('Override');
        $assignment = app(ShiftAssignmentService::class)->create($this->shift, $this->overridePayload($member));
        $url = route('team.shifts.assignment-candidates', $this->shift).'?override=1&hours_mode=full_shift';
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->getJson($url)->assertForbidden();
        $this->put($this->updateUrl(), $this->shiftPayload(['assignment_additions' => [$this->overridePayload($this->member('New'))]]))->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->assertServiceValidation(fn () => $member->delete(), 'team_engagement_id');
        $this->event->lock();
        $this->getJson($url)->assertForbidden();
        $this->post($this->storeUrl(), $this->overridePayload($this->member('Locked')))->assertForbidden();
        try {
            app(ShiftAssignmentService::class)->create($this->shift, $this->overridePayload($this->member('Service locked')));
            $this->fail('Service must refuse a locked override.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->event->unlock();
        $assignment->breaks()->create(['duration_minutes' => 15, 'starts_at' => '2026-10-01T12:00', 'sort_order' => 0]);
        app(EventService::class)->delete($this->event);
        $this->assertModelMissing($assignment);
        $this->assertModelMissing($member);
        $this->assertDatabaseCount('shift_assignment_breaks', 0);
    }

    private function overridePayload(TeamEngagement $member): array
    {
        return ['override' => true, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'];
    }

    private function createPayload(array $overrides = []): array
    {
        return $this->shiftPayload([
            'name' => 'New shift',
            'slots' => [
                ['id' => null, 'client_key' => 'draft-1', 'role_id' => $this->role->id, 'needed' => 1],
                ['id' => null, 'client_key' => 'draft-2', 'role_id' => $this->role->id, 'needed' => 1],
            ],
            ...$overrides,
        ]);
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

    private function saveHours(ShiftAssignment $assignment, array $hours): TestResponse
    {
        $shift = $this->shift->fresh();
        $context = array_intersect_key($hours, array_flip(['return_tab', 'schedule_date']));
        $hours = array_diff_key($hours, $context);

        return $this->put($this->updateUrl(), $this->shiftPayload([
            'starts_at' => $shift->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $shift->ends_at->format('Y-m-d\TH:i'),
            ...$context,
            'assignment_updates' => [['id' => $assignment->id, ...$hours]],
        ]));
    }

    private function shiftPayload(array $overrides = []): array
    {
        return ['name' => 'Show run', 'location_id' => $this->shift->location_id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', ...$overrides];
    }
}
