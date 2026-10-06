<?php

namespace Tests\Feature;

use App\Http\Resources\ShiftCopyResource;
use App\Models\Event;
use App\Models\Location;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamShiftBreaksTest extends TestCase
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
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-05', 'timezone' => 'America/Vancouver']);
        $this->location = $this->event->locations()->create(['name' => 'Main stage']);
        $this->role = Role::create(['name' => 'Crew']);
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_create_saves_breaks_without_names_and_exposes_server_options_and_ordered_values(): void
    {
        $this->get(route('team.shifts.create'))->assertInertia(fn (Assert $page) => $page
            ->where('breakOptions.durations', [15, 30, 45, 60])->where('breakOptions.default_duration', 15));
        $this->post(route('team.shifts.store', $this->event), $this->payload([
            $this->breakRow(), $this->breakRow('2026-10-03T14:00', 30),
        ]))->assertSessionHasNoErrors();
        $shift = Shift::sole();
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->has('shift.breaks', 2)->missing('shift.breaks.0.name')
            ->where('shift.breaks.0.duration_minutes', 15)->where('shift.breaks.0.starts_at', '2026-10-03T15:30')
            ->where('shift.breaks.1.sort_order', 1));
        $payload = $this->payload();
        unset($payload['breaks']);
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, Shift::latest('id')->first()->breaks()->count());
    }

    public function test_longer_breaks_save_without_names_and_deduct_their_full_duration(): void
    {
        $this->post(route('team.shifts.store', $this->event), $this->payload([
            $this->breakRow('2026-10-03T15:00', 45), $this->breakRow('2026-10-03T16:00', 60),
        ]))->assertSessionHasNoErrors();
        $shift = Shift::sole();
        $this->assertSame([45, 60], $shift->breaks()->pluck('duration_minutes')->all());
        $this->assign($shift, 'Full shift', '2026-10-03T14:00', '2026-10-03T22:00');
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.scheduled_minutes', 375)->missing('shift.breaks.0.name'));
        $this->getJson($this->rosterUrl())->assertJsonPath('data.0.assignments.0.scheduled_minutes', 375);
        $first = $shift->breaks()->first();
        $this->put($this->updateUrl($shift), $this->updatePayload([
            ['id' => $first->id, ...$this->breakRow('2026-10-03T21:00', 60)],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(60, $first->fresh()->duration_minutes);
        $this->put($this->updateUrl($shift), $this->updatePayload([
            ['id' => $first->id, ...$this->breakRow('2026-10-03T21:01', 60)],
        ]))->assertSessionHasErrors('breaks.0.starts_at');
        $this->post(route('team.shifts.store', $this->event), $this->payload([
            $this->breakRow('2026-10-03T15:00', 60), $this->breakRow('2026-10-03T15:45', 45),
        ]))->assertSessionHasErrors('breaks.1.starts_at');
    }

    public function test_update_retains_identity_and_insertion_order_adds_and_deletes_and_distinguishes_omission(): void
    {
        $shift = $this->shift([$this->breakRow(), $this->breakRow('2026-10-03T18:30', 30)]);
        [$first, $removed] = $shift->breaks()->get()->all();
        $rows = [
            ['id' => $first->id, ...$this->breakRow('2026-10-03T16:00', 30)],
            $this->breakRow('2026-10-03T19:00', 15),
        ];
        $this->put($this->updateUrl($shift), $this->payload($rows))->assertSessionHasNoErrors();
        $new = $shift->breaks()->whereKeyNot($first->id)->sole();
        $this->assertModelMissing($removed);
        $this->assertSame(30, $first->fresh()->duration_minutes);
        $this->assertSame(0, $first->fresh()->sort_order);
        $this->assertSame(2, $new->sort_order);
        $rows[1]['id'] = $new->id;
        $this->put($this->updateUrl($shift), $this->payload(array_reverse($rows)))->assertSessionHasNoErrors();
        $this->assertSame([$first->id, $new->id], $shift->breaks()->pluck('id')->all());
        $payload = $this->payload();
        unset($payload['breaks']);
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $shift->breaks()->count());
        $this->put($this->updateUrl($shift), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(0, $shift->breaks()->count());
    }

    public function test_invalid_row_fields_fail_form_requests_without_changing_details_or_children(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        foreach ([
            ['name' => 'Retired field'],
            ['starts_at' => null], ['starts_at' => '15:30'], ['starts_at' => '2026-02-30T15:30'],
            ['starts_at' => '2026-10-03T15:30:00'], ['duration_minutes' => null],
            ['duration_minutes' => 0], ['duration_minutes' => 10], ['duration_minutes' => 90],
            ['duration_minutes' => 15.5], ['duration_minutes' => 'many'],
            ['id' => 999999], ['shift_id' => $shift->id], ['event_id' => $this->event->id],
            ['sort_order' => 0], ['paid' => true], ['ends_at' => '2026-10-03T16:00'],
        ] as $change) {
            $payload = $this->payload([array_replace($this->breakRow(), $change)]);
            $payload['name'] = 'Must not save';
            $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasErrors();
            $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors();
            $this->assertDatabaseCount('shifts', 1);
            $this->assertSame('Show run', $shift->fresh()->name);
            $this->assertSame(1, $shift->breaks()->count());
        }
        foreach (['starts_at', 'duration_minutes'] as $field) {
            $row = $this->breakRow();
            unset($row[$field]);
            $this->post(route('team.shifts.store', $this->event), $this->payload([$row]))->assertSessionHasErrors('breaks.0.'.$field);
            $this->put($this->updateUrl($shift), $this->payload([$row]))->assertSessionHasErrors('breaks.0.'.$field);
        }
        foreach ([null, 'bad', [3 => $this->breakRow()], [null]] as $rows) {
            $payload = array_replace($this->payload(), ['breaks' => $rows]);
            $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasErrors();
            $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('shift_breaks', 1);
    }

    public function test_breaks_fit_inclusive_boundaries_and_overnight_dates(): void
    {
        $this->post(route('team.shifts.store', $this->event), $this->payload([
            $this->breakRow('2026-10-03T14:00'), $this->breakRow('2026-10-03T21:30', 30),
        ]))->assertSessionHasNoErrors();
        foreach (['2026-10-03T13:59', '2026-10-03T21:46', '2026-10-03T22:00', '2026-10-04T00:00'] as $time) {
            $this->post(route('team.shifts.store', $this->event), $this->payload([$this->breakRow($time)]))
                ->assertSessionHasErrors('breaks.0.starts_at');
        }
        $this->post(route('team.shifts.store', $this->event), array_replace($this->payload([
            $this->breakRow('2026-10-04T01:30', 30),
        ]), ['starts_at' => '2026-10-03T23:00', 'ends_at' => '2026-10-04T02:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shift_breaks', ['starts_at' => '2026-10-04 01:30:00']);
    }

    public function test_overlapping_breaks_are_refused_in_either_payload_order_and_back_to_back_is_valid(): void
    {
        $overlapping = [$this->breakRow('2026-10-03T15:30', 30), $this->breakRow('2026-10-03T15:45')];
        foreach ([$overlapping, array_reverse($overlapping), [$this->breakRow(), $this->breakRow()]] as $rows) {
            $this->post(route('team.shifts.store', $this->event), $this->payload($rows))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('shifts', 0);
        $this->post(route('team.shifts.store', $this->event), $this->payload([
            $this->breakRow('2026-10-03T15:30', 30), $this->breakRow('2026-10-03T16:00'),
        ]))->assertSessionHasNoErrors();
    }

    public function test_proposed_bounds_validate_retained_breaks_and_compound_failures_are_atomic(): void
    {
        $shift = $this->shift([$this->breakRow('2026-10-03T21:30', 30)]);
        $slot = $shift->roleSlots()->sole();
        $payload = array_replace($this->payload(), ['name' => 'Changed', 'ends_at' => '2026-10-03T21:45', 'slots' => [
            ['id' => $slot->id, 'role_id' => $this->role->id, 'needed' => 3],
        ]]);
        unset($payload['breaks']);
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors('ends_at');
        $payload['breaks'] = [['id' => $shift->breaks()->sole()->id, ...$this->breakRow('2026-10-03T21:30', 30)]];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors('breaks.0.starts_at');
        $this->assertSame('Show run', $shift->fresh()->name);
        $this->assertSame(1, $slot->fresh()->needed);
        $this->assertSame(30, $shift->breaks()->sole()->duration_minutes);
        $payload['breaks'][0]['starts_at'] = '2026-10-03T21:15';
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Changed', $shift->fresh()->name);
    }

    public function test_foreign_duplicate_and_stale_ids_are_rejected_and_event_scope_remains_intact(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $other = $this->shift([$this->breakRow('2026-10-03T18:00')]);
        $foreign = $other->breaks()->sole();
        $this->put($this->updateUrl($shift), $this->payload([['id' => $foreign->id, ...$this->breakRow()]]))
            ->assertSessionHasErrors('breaks.0.id');
        $own = $shift->breaks()->sole();
        $this->put($this->updateUrl($shift), $this->payload([
            ['id' => $own->id, ...$this->breakRow()], ['id' => $own->id, ...$this->breakRow('2026-10-03T19:00')],
        ]))->assertSessionHasErrors('breaks.0.id');
        $otherEvent = Event::create(['name' => 'Other', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-05', 'timezone' => 'America/Vancouver']);
        $otherLocation = $otherEvent->locations()->create(['name' => 'Gate']);
        $foreignShift = app(ShiftService::class)->create($otherEvent, array_replace($this->payload([$this->breakRow()]), ['location_id' => $otherLocation->id]));
        $this->put($this->updateUrl($shift), $this->payload([['id' => $foreignShift->breaks()->sole()->id, ...$this->breakRow()]]))
            ->assertSessionHasErrors('breaks.0.id');
        $this->put(route('team.shifts.update', [$otherEvent, $shift]), $this->payload())->assertNotFound();
        $this->get(route('team.shifts.show', $foreignShift))->assertNotFound();
        $stale = $this->payload([['id' => $own->id, ...$this->breakRow()]]);
        $own->delete();
        $this->put($this->updateUrl($shift), $stale)->assertSessionHasErrors('breaks.0.id');
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($shift, $stale), 'breaks.0.id');
        $this->user->setCurrentEvent($otherEvent);
        $this->put(route('team.shifts.update', [$otherEvent, $foreignShift]), array_replace($this->payload(), ['location_id' => $otherLocation->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->event->id, app(OrganizationContext::class)->organization()->active_event_id);
    }

    public function test_services_recheck_containment_overlaps_and_event_locks(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $data = $this->payload();
        unset($data['breaks']);
        $data['starts_at'] = '2026-10-03T16:00';
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($shift, $data), 'starts_at');
        $overlap = $this->payload([$this->breakRow(), $this->breakRow()]);
        $this->assertServiceValidation(fn () => app(ShiftService::class)->update($shift, $overlap), 'breaks.1.starts_at');
        $this->assertServiceValidation(fn () => app(ShiftService::class)->create($this->event, $overlap), 'breaks.1.starts_at');
        $this->event->lock();
        foreach ([fn () => app(ShiftService::class)->create($this->event, $this->payload()), fn () => app(ShiftService::class)->update($shift, $this->payload())] as $write) {
            try {
                $write();
                $this->fail('Locked event must refuse service writes.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('shift_breaks', 1);
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_view_edit_permissions_and_locked_states_prevent_all_break_writes(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->has('shift.breaks', 1)->where('canManage', false));
        $this->assertWritesForbidden($shift);
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->get(route('team.shifts.show', $shift))->assertForbidden();
        $this->getJson($this->rosterUrl())->assertForbidden();
        $this->get(route('team.members.show', $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()))
            ->assertInertia(fn (Assert $page) => $page->missing('shift')->missing('breaks'));
        $this->event->lock();
        foreach ([false, true] as $admin) {
            $admin ? $this->grantAdminAccess($this->user) : $this->grantRoleAccess($this->user, ['scheduling.edit']);
            $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
                ->where('event.is_locked', true)->has('shift.breaks', 1));
            $this->assertWritesForbidden($shift);
        }
        $this->assertDatabaseCount('shift_breaks', 1);
    }

    public function test_every_break_deducts_from_own_hours_in_both_rosters_and_never_changes_attendance_intervals(): void
    {
        $shift = $this->shift([$this->breakRow(), $this->breakRow('2026-10-03T18:30', 30)]);
        $full = $this->assign($shift, 'Full shift', '2026-10-03T14:00', '2026-10-03T22:00');
        $this->assign($shift, 'Later hours', '2026-10-03T18:00', '2026-10-03T22:00');
        $this->assign($shift, 'Short assignment', '2026-10-03T14:00', '2026-10-03T14:30');
        $detached = $this->assign($shift, 'Detached', '2026-10-03T17:00', '2026-10-03T19:00');
        $detached->update(['shift_role_slot_id' => null]);
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignments.0.scheduled_minutes', 435)->where('shift.assignments.1.scheduled_minutes', 210)
            ->where('shift.assignments.2.scheduled_minutes', 30)->where('shift.assignments.3.scheduled_minutes', 90)
            ->where('shift.assignments.3.is_extra', true)->where('shift.assignment_count', 4));
        $this->getJson($this->rosterUrl())->assertOk()->assertJsonPath('data.0.assignments.0.scheduled_minutes', 435)
            ->assertJsonPath('data.0.assignments.1.scheduled_minutes', 210)->assertJsonPath('data.0.assignments.2.scheduled_minutes', 30)
            ->assertJsonPath('data.0.assignments.3.scheduled_minutes', 90)->assertJsonMissingPath('data.0.breaks');
        $first = $shift->breaks()->first();
        $this->put($this->updateUrl($shift), $this->updatePayload([['id' => $first->id, ...$this->breakRow(duration: 30)]]))->assertSessionHasNoErrors();
        $this->getJson($this->rosterUrl())->assertJsonPath('data.0.assignments.0.scheduled_minutes', 435);
        $this->put($this->updateUrl($shift), $this->updatePayload())->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page->where('shift.assignments.0.scheduled_minutes', 435));
        $this->assertSame('2026-10-03T14:00', $full->fresh()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame('2026-10-03T22:00', $full->fresh()->ends_at->format('Y-m-d\TH:i'));
    }

    public function test_overnight_hours_use_full_assignment_on_either_selected_day(): void
    {
        $shift = app(ShiftService::class)->create($this->event, array_replace($this->payload([$this->breakRow('2026-10-04T00:15', 30)]), [
            'starts_at' => '2026-10-03T23:00', 'ends_at' => '2026-10-04T01:00',
        ]));
        $this->assign($shift, 'Overnight', '2026-10-03T23:00', '2026-10-04T01:00');
        foreach (['2026-10-03', '2026-10-04'] as $day) {
            $this->getJson($this->rosterUrl($day))->assertOk()->assertJsonPath('data.0.assignments.0.scheduled_minutes', 90);
        }
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page->where('shift.assignments.0.scheduled_minutes', 90));
    }

    public function test_roster_break_sums_stay_batched_and_list_does_not_hydrate_break_or_person_data(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        for ($i = 0; $i < 30; $i++) {
            $shift = $this->shift([$this->breakRow()]);
            $this->assign($shift, 'Crew '.$i, '2026-10-03T14:00', '2026-10-03T22:00');
        }
        $queries = [];
        $this->getJson($this->rosterUrl())->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('data.29.assignments.0.scheduled_minutes', 465);
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'shift_assignment_breaks')));
        $queries = [];
        $this->getJson(route('team.scheduling.shifts', ['start' => 0, 'length' => 25]))->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonMissingPath('data.0.assignments')->assertJsonMissingPath('data.0.breaks');
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_contains($sql, 'shift_breaks')));
    }

    public function test_shift_delete_cascades_breaks_and_database_constraints_use_savepoints(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $break = $shift->breaks()->sole();
        foreach (['duration_minutes' => 90, 'sort_order' => -1, 'shift_id' => $shift->id + 999999] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('shift_breaks')->where('id', $break->id)->update([$field => $value]));
                $this->fail('Database constraint must refuse the write.');
            } catch (QueryException) {
                $this->assertSame(15, $break->fresh()->duration_minutes);
                $this->assertSame(0, $break->fresh()->sort_order);
            }
        }
        $this->delete(route('team.shifts.destroy', [$this->event, $shift]))->assertRedirect();
        $this->assertModelMissing($break);
        $this->assertModelExists($this->event);
    }

    public function test_assignment_snapshots_only_fitting_defaults_and_explicit_empty_breaks_stay_empty(): void
    {
        $shift = $this->shift([$this->breakRow(), $this->breakRow('2026-10-03T18:30', 30)]);
        $late = $this->assign($shift, 'Late', '2026-10-03T18:00', '2026-10-03T22:00');
        $this->assertSame([$shift->breaks()->get()->last()->id], $late->breaks()->pluck('shift_break_id')->all());
        $person = $this->assign($shift, 'No breaks', '2026-10-03T14:00', '2026-10-03T22:00');
        $this->put($this->updateUrl($shift), [...$this->updatePayload(), 'assignment_updates' => [
            ['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => []],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(0, $person->breaks()->count());
        $this->assertSame(1, $late->breaks()->count());
        $this->assertNull($late->breaks()->sole()->shift_break_id);
    }

    public function test_personal_break_validation_rolls_back_details_headcount_defaults_and_entire_roster(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $person = $this->assign($shift, 'Person', '2026-10-03T14:00', '2026-10-03T22:00');
        $removed = $this->assign($shift, 'Keep on failure', '2026-10-03T14:00', '2026-10-03T22:00');
        $slot = $shift->roleSlots()->sole();
        $source = $shift->breaks()->sole();
        $saved = $person->breaks()->sole();
        foreach ([
            [$this->breakRow('2026-10-03T13:59')],
            [$this->breakRow('2026-10-03T21:46')],
            [$this->breakRow(), $this->breakRow('2026-10-03T15:35')],
            [['duration_minutes' => 10, 'starts_at' => '2026-10-03T16:00']],
            [['duration_minutes' => 15, 'starts_at' => '']],
            [['id' => $removed->breaks()->sole()->id, ...$this->breakRow()]],
        ] as $breaks) {
            $this->put($this->updateUrl($shift), [...$this->payload([['id' => $source->id, ...$this->breakRow(duration: 30)]]),
                'name' => 'Must roll back', 'slots' => [['id' => $slot->id, 'role_id' => $this->role->id, 'needed' => 3]],
                'assignment_updates' => [['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => $breaks]],
                'assignment_removals' => [$removed->id],
            ])->assertSessionHasErrors();
            $this->assertSame('Show run', $shift->fresh()->name);
            $this->assertSame(1, $slot->fresh()->needed);
            $this->assertSame(15, $source->fresh()->duration_minutes);
            $this->assertModelExists($removed);
            $this->assertModelExists($saved);
        }
        $this->put($this->updateUrl($shift), [...$this->updatePayload([['id' => $source->id, ...$this->breakRow()]]),
            'assignment_updates' => [['id' => $person->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-03T16:00', 'ends_at' => '2026-10-03T22:00']],
        ])->assertSessionHasErrors('assignment_updates.0.breaks.0.starts_at');
        $this->assertSame('2026-10-03T14:00', $person->fresh()->starts_at->format('Y-m-d\TH:i'));
    }

    public function test_bulk_edit_replays_pre_edit_source_and_skips_changed_removed_and_outside_people(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $source = $shift->breaks()->sole();
        $unchanged = $this->assign($shift, 'Unchanged', '2026-10-03T14:00', '2026-10-03T22:00');
        $changed = $this->assign($shift, 'Changed', '2026-10-03T14:00', '2026-10-03T22:00');
        $changed->breaks()->sole()->update(['starts_at' => '2026-10-03T16:00']);
        $removed = $this->assign($shift, 'Removed own', '2026-10-03T14:00', '2026-10-03T22:00');
        $removed->breaks()->delete();
        $short = $this->assign($shift, 'Short', '2026-10-03T15:00', '2026-10-03T16:00');
        $next = $this->breakRow('2026-10-03T18:00', 30);
        $copy = $unchanged->breaks()->sole();
        $payload = [...$this->updatePayload([['id' => $source->id, ...$next]]),
            'break_operations' => [['type' => 'default', 'source' => (string) $source->id, 'break' => $next, 'apply' => true]],
            'assignment_updates' => [['id' => $unchanged->id, 'hours_mode' => 'full_shift', 'breaks' => [['id' => $copy->id, 'shift_break_id' => $source->id, ...$next]]]],
        ];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-03T18:00', $copy->fresh()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame('2026-10-03T16:00', $changed->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame(0, $removed->breaks()->count());
        $this->assertSame('2026-10-03T15:30', $short->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame(1, $unchanged->breaks()->count());
        // A crafted final list cannot force an ineligible bulk result.
        $payload['breaks'][0]['starts_at'] = '2026-10-03T19:00';
        $payload['break_operations'][0]['break']['starts_at'] = '2026-10-03T19:00';
        $payload['assignment_updates'][0]['breaks'][0]['starts_at'] = '2026-10-03T19:00';
        $payload['assignment_updates'][] = ['id' => $changed->id, 'hours_mode' => 'full_shift', 'breaks' => [
            ['id' => $changed->breaks()->sole()->id, 'shift_break_id' => $source->id, ...$this->breakRow('2026-10-03T19:00', 30)],
        ]];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors('break_operations');
        $this->assertSame('2026-10-03T18:00', $source->fresh()->starts_at->format('Y-m-d\TH:i'));
    }

    public function test_bulk_add_draft_source_mapping_and_delete_only_unchanged_copies(): void
    {
        $shift = $this->shift();
        $free = $this->assign($shift, 'Free', '2026-10-03T14:00', '2026-10-03T22:00');
        $busy = $this->assign($shift, 'Busy', '2026-10-03T14:00', '2026-10-03T22:00');
        $busy->breaks()->create([...$this->breakRow(), 'sort_order' => 0]);
        $next = $this->breakRow();
        $this->put($this->updateUrl($shift), [...$this->updatePayload([['client_key' => 'draft-break-1', ...$next]]),
            'break_operations' => [['type' => 'default', 'source' => 'draft-break-1', 'break' => $next, 'apply' => true]],
            'assignment_updates' => [['id' => $free->id, 'hours_mode' => 'full_shift', 'breaks' => [['shift_break_key' => 'draft-break-1', ...$next]]]],
        ])->assertSessionHasNoErrors();
        $source = $shift->breaks()->sole();
        $this->assertSame($source->id, $free->breaks()->sole()->shift_break_id);
        $this->assertNull($busy->breaks()->sole()->shift_break_id);
        $changed = $this->assign($shift, 'Moved copy', '2026-10-03T14:00', '2026-10-03T22:00');
        $changed->breaks()->sole()->update(['starts_at' => '2026-10-03T16:00']);
        $this->put($this->updateUrl($shift), [...$this->updatePayload(),
            'break_operations' => [['type' => 'default', 'source' => (string) $source->id, 'break' => null, 'apply' => true]],
            'assignment_updates' => [
                ['id' => $free->id, 'hours_mode' => 'full_shift', 'breaks' => []],
                ['id' => $changed->id, 'hours_mode' => 'full_shift', 'breaks' => [
                    ['id' => $changed->breaks()->sole()->id, 'shift_break_id' => null, ...$this->breakRow('2026-10-03T16:00')],
                ]],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $free->breaks()->count());
        $this->assertSame(1, $changed->breaks()->count());
        $this->assertNull($changed->breaks()->sole()->shift_break_id);
        $this->assertSame(1, $busy->breaks()->count());
    }

    public function test_new_assignment_saves_its_draft_snapshot_after_defaults_change_and_copy_refs_remap(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $person = $this->assign($shift, 'Snapshot', '2026-10-03T14:00', '2026-10-03T22:00');
        $memberId = $person->team_engagement_id;
        $person->delete();
        $source = $shift->breaks()->sole();
        $this->put($this->updateUrl($shift), [...$this->updatePayload([['id' => $source->id, ...$this->breakRow('2026-10-03T18:00')]]),
            'assignment_additions' => [['shift_role_slot_id' => $shift->roleSlots()->sole()->id, 'team_engagement_id' => $memberId, 'hours_mode' => 'full_shift',
                'breaks' => [['shift_break_id' => $source->id, ...$this->breakRow()]],
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-03T15:30', $shift->assignments()->sole()->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $sourceDraft = app(ShiftService::class)->copyDraft($shift);
        $snapshot = (new ShiftCopyResource($sourceDraft))->resolve();
        $this->assertSame(0, $snapshot['assignments'][0]['breaks'][0]['source_break_index']);
        $create = [...$this->payload([['client_key' => 'draft-break-9', ...$this->breakRow('2026-10-03T18:00')]]),
            'slots' => [['client_key' => 'draft-9', 'role_id' => $this->role->id, 'needed' => 1]],
            'assignment_additions' => [['slot_key' => 'draft-9', 'team_engagement_id' => $memberId, 'hours_mode' => 'full_shift',
                'breaks' => [['shift_break_key' => 'draft-break-9', ...$this->breakRow()]],
            ]],
        ];
        $shift->delete();
        $this->post(route('team.shifts.store', $this->event), $create)->assertSessionHasNoErrors();
        $copy = Shift::sole();
        $this->assertSame($copy->breaks()->sole()->id, $copy->assignments()->sole()->breaks()->sole()->shift_break_id);
    }

    public function test_migration_backfills_fitting_snapshots_including_locked_events_and_guards_lossy_rollback(): void
    {
        $shift = $this->shift([$this->breakRow(), $this->breakRow('2026-10-03T18:30', 30)]);
        $full = $this->assign($shift, 'Full', '2026-10-03T14:00', '2026-10-03T22:00');
        $late = $this->assign($shift, 'Late', '2026-10-03T18:00', '2026-10-03T22:00');
        $this->event->lock();
        DB::table('shift_assignment_breaks')->delete();
        $migration = require database_path('migrations/2026_10_05_000001_create_shift_assignment_breaks_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame(2, $full->breaks()->count());
        $this->assertSame(1, $late->breaks()->count());
        $this->assertSame(30, $late->breaks()->sole()->duration_minutes);
        $this->assertSame($shift->breaks()->get()->last()->id, $late->breaks()->sole()->shift_break_id);
        try {
            $migration->down();
            $this->fail('Rollback must preserve personal snapshots.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('forward migration', $exception->getMessage());
        }
        $this->assertDatabaseCount('shift_assignment_breaks', 3);
        foreach (['duration_minutes' => 90, 'sort_order' => -1, 'shift_assignment_id' => $full->id + 99999] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('shift_assignment_breaks')->where('shift_assignment_id', $full->id)->update([$field => $value]));
                $this->fail('Constraint must reject invalid personal breaks.');
            } catch (QueryException) {
                $this->assertSame(2, $full->breaks()->count());
            }
        }
        $shift->delete();
        $this->assertDatabaseCount('shift_assignment_breaks', 0);
    }

    public function test_invalid_intermediate_personal_draft_can_be_corrected_and_empty_breaks_committed(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $person = $this->assign($shift, 'Draft correction', '2026-10-03T14:00', '2026-10-03T22:00');
        $source = $shift->breaks()->sole();
        $copy = $person->breaks()->sole();
        $final = ['id' => $copy->id, 'shift_break_id' => null, ...$this->breakRow('2026-10-03T16:00')];
        $operation = ['type' => 'person', 'assignment_key' => $person->id, 'starts_at' => '2026-10-03T14:00', 'ends_at' => '2026-10-03T22:00'];
        $payload = [...$this->updatePayload([['id' => $source->id, ...$this->breakRow()]]),
            'assignment_updates' => [['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => [$final]]],
            'break_operations' => [[...$operation, 'breaks' => [[...$final, 'starts_at' => '']]], [...$operation, 'breaks' => [$final]]],
        ];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-03T16:00', $copy->fresh()->starts_at->format('Y-m-d\TH:i'));
        $this->assertNull($copy->fresh()->shift_break_id);
        $payload['assignment_updates'][0]['breaks'] = [];
        $payload['break_operations'] = [[...$operation, 'breaks' => []]];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $person->breaks()->count());
    }

    public function test_personal_breaks_are_visible_with_view_and_all_writes_require_edit_and_an_unlocked_event(): void
    {
        $shift = $this->shift([$this->breakRow()]);
        $person = $this->assign($shift, 'Restricted', '2026-10-03T14:00', '2026-10-03T22:00');
        $payload = [...$this->updatePayload(), 'assignment_updates' => [['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => []]]];
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page->has('shift.assignments.0.breaks', 1)->where('canManage', false));
        $this->put($this->updateUrl($shift), $payload)->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->event->lock();
        $this->put($this->updateUrl($shift), $payload)->assertForbidden();
        $this->assertSame(1, $person->breaks()->count());
    }

    private function assertWritesForbidden(Shift $shift): void
    {
        $this->post(route('team.shifts.store', $this->event), $this->payload([$this->breakRow()]))->assertForbidden();
        $this->put($this->updateUrl($shift), $this->payload())->assertForbidden();
        $this->delete(route('team.shifts.destroy', [$this->event, $shift]))->assertForbidden();
    }

    private function assertServiceValidation(callable $write, string $field): void
    {
        try {
            $write();
            $this->fail('Service must reject invalid break state.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_break_length_migration_rollback_restores_the_schema_and_preserves_rows_and_sequences(): void
    {
        $shift = $this->shift([$this->breakRow(), $this->breakRow('2026-10-03T18:30', 30)]);
        $discarded = $shift->breaks()->create([...$this->breakRow('2026-10-03T20:00'), 'sort_order' => 2]);
        $lastId = $discarded->id;
        $discarded->delete();
        $columns = ['id', 'shift_id', 'duration_minutes', 'starts_at', 'sort_order', 'created_at', 'updated_at'];
        $before = DB::table('shift_breaks')->orderBy('id')->get($columns)->toJson();
        $migration = require database_path('migrations/2026_10_03_000003_simplify_shift_breaks_and_expand_lengths.php');
        $migration->down();
        $this->assertTrue(Schema::hasColumn('shift_breaks', 'name'));
        $this->assertSame(['', ''], DB::table('shift_breaks')->orderBy('id')->pluck('name')->all());
        $this->assertSame($before, DB::table('shift_breaks')->orderBy('id')->get($columns)->toJson());
        try {
            DB::transaction(fn () => DB::table('shift_breaks')->where('shift_id', $shift->id)->update(['duration_minutes' => 45]));
            $this->fail('The legacy CHECK must refuse longer breaks.');
        } catch (QueryException) {
            $this->assertSame($before, DB::table('shift_breaks')->orderBy('id')->get($columns)->toJson());
        }
        $migration->up();
        $this->assertFalse(Schema::hasColumn('shift_breaks', 'name'));
        $this->assertSame($before, DB::table('shift_breaks')->orderBy('id')->get($columns)->toJson());
        $next = $shift->breaks()->create([...$this->breakRow('2026-10-03T20:00', 60), 'sort_order' => 2]);
        $this->assertGreaterThan($lastId, $next->id);
    }

    public function test_break_length_migration_refuses_lossy_rollback_before_any_schema_or_data_change(): void
    {
        $shift = $this->shift([$this->breakRow(duration: 45)]);
        $break = $shift->breaks()->sole();
        $migration = require database_path('migrations/2026_10_03_000003_simplify_shift_breaks_and_expand_lengths.php');
        foreach ([45, 60] as $duration) {
            $break->update(['duration_minutes' => $duration]);
            $before = DB::table('shift_breaks')->get()->toJson();
            try {
                $migration->down();
                $this->fail('Longer breaks cannot be downgraded losslessly.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Cannot roll back', $exception->getMessage());
            }
            $this->assertFalse(Schema::hasColumn('shift_breaks', 'name'));
            $this->assertSame($before, DB::table('shift_breaks')->get()->toJson());
        }
    }

    public function test_mass_add_stages_independent_breaks_only_for_current_people_whose_hours_fit(): void
    {
        $shift = $this->shift();
        $full = $this->assign($shift, 'Full', '2026-10-03T14:00', '2026-10-03T22:00');
        $other = $this->assign($shift, 'Other', '2026-10-03T14:00', '2026-10-03T22:00');
        $short = $this->assign($shift, 'Short', '2026-10-03T15:00', '2026-10-03T15:40');
        $break = $this->breakRow(duration: 30);
        $payload = [...$this->updatePayload(),
            'break_operations' => [['type' => 'mass', 'assignment_keys' => [$full->id, $other->id, $short->id], 'break' => $break]],
            'assignment_updates' => array_map(fn ($person) => ['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => [['shift_break_id' => null, ...$break]]], [$full, $other]),
        ];
        $this->put($this->updateUrl($shift), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shift_breaks', 0);
        $this->assertSame(0, $short->breaks()->count());
        foreach ([$full, $other] as $person) {
            $this->assertNull($person->breaks()->sole()->shift_break_id);
            $this->assertSame(30, $person->breaks()->sole()->duration_minutes);
        }
    }

    public function test_mass_add_blocks_everyone_on_any_existing_overlap_and_leaves_the_entire_save_unchanged(): void
    {
        $shift = $this->shift();
        $free = $this->assign($shift, 'Free', '2026-10-03T14:00', '2026-10-03T22:00');
        $busy = $this->assign($shift, 'Busy', '2026-10-03T14:00', '2026-10-03T22:00');
        // This may have been saved after the editor loaded. Omitting it from the submitted list must not bypass the check.
        $saved = $busy->breaks()->create([...$this->breakRow(), 'sort_order' => 0]);
        foreach (['2026-10-03T15:30', '2026-10-03T15:15', '2026-10-03T15:40'] as $time) {
            $break = $this->breakRow($time, 30);
            $payload = [...$this->updatePayload(), 'name' => 'Must not save',
                'break_operations' => [['type' => 'mass', 'assignment_keys' => [$free->id, $busy->id], 'break' => $break]],
                'assignment_updates' => array_map(fn ($person) => ['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => [$break]], [$free, $busy]),
            ];
            $response = $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors('break_operations.0');
            $response->assertSessionHas('errors', fn ($errors) => $errors->first('break_operations.0') === __('team.scheduling.breaks.errors.mass_conflict'));
            $this->assertSame('Show run', $shift->fresh()->name);
            $this->assertSame(0, $free->breaks()->count());
            $this->assertSame($saved->id, $busy->breaks()->sole()->id);
            $this->assertDatabaseCount('shift_breaks', 0);
        }
        // Adjacent intervals do not overlap.
        $next = $this->breakRow('2026-10-03T15:45');
        $this->put($this->updateUrl($shift), [...$this->updatePayload(),
            'break_operations' => [['type' => 'mass', 'assignment_keys' => [$free->id, $busy->id], 'break' => $next]],
            'assignment_updates' => [
                ['id' => $free->id, 'hours_mode' => 'full_shift', 'breaks' => [$next]],
                ['id' => $busy->id, 'hours_mode' => 'full_shift', 'breaks' => [
                    ['id' => $saved->id, 'shift_break_id' => null, ...$this->breakRow()], $next,
                ]],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $busy->breaks()->count());
        $this->assertSame(1, $free->breaks()->count());
    }

    public function test_mass_add_rejects_foreign_targets_malformed_operations_and_locked_events(): void
    {
        $shift = $this->shift();
        $person = $this->assign($shift, 'Current', '2026-10-03T14:00', '2026-10-03T22:00');
        $foreign = $this->assign($this->shift(), 'Foreign', '2026-10-03T14:00', '2026-10-03T22:00');
        $base = [...$this->updatePayload(), 'break_operations' => [['type' => 'mass', 'assignment_keys' => [$person->id], 'break' => $this->breakRow()]],
            'assignment_updates' => [['id' => $person->id, 'hours_mode' => 'full_shift', 'breaks' => [$this->breakRow()]]],
        ];
        foreach ([[$foreign->id], [$person->id, $person->id], []] as $keys) {
            $payload = $base;
            $payload['break_operations'][0]['assignment_keys'] = $keys;
            $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors();
        }
        foreach ([null, ['duration_minutes' => 10, 'starts_at' => '2026-10-03T15:30'], ['duration_minutes' => 15, 'starts_at' => 'bad']] as $break) {
            $payload = $base;
            $payload['break_operations'][0]['break'] = $break;
            $this->put($this->updateUrl($shift), $payload)->assertSessionHasErrors();
        }
        $this->event->lock();
        $this->put($this->updateUrl($shift), $base)->assertForbidden();
        $this->assertDatabaseCount('shift_assignment_breaks', 0);
    }

    public function test_create_mass_add_targets_draft_people_at_the_time_of_the_add_without_affecting_later_people(): void
    {
        $first = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => Person::create(['name' => 'First', 'email' => 'first@example.test'])->id,
            'role_id' => $this->role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $later = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => Person::create(['name' => 'Later', 'email' => 'later@example.test'])->id,
            'role_id' => $this->role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $break = $this->breakRow();
        $this->post(route('team.shifts.store', $this->event), [...$this->payload(),
            'assignment_additions' => [
                ['client_key' => -1, 'role_id' => $this->role->id, 'team_engagement_id' => $first->id, 'hours_mode' => 'full_shift', 'breaks' => [$break]],
                ['client_key' => -2, 'role_id' => $this->role->id, 'team_engagement_id' => $later->id, 'hours_mode' => 'full_shift', 'breaks' => []],
            ],
            'break_operations' => [
                ['type' => 'person', 'assignment_key' => -1, 'starts_at' => '2026-10-03T14:00', 'ends_at' => '2026-10-03T22:00', 'breaks' => []],
                ['type' => 'mass', 'assignment_keys' => [-1], 'break' => $break],
                ['type' => 'person', 'assignment_key' => -2, 'starts_at' => '2026-10-03T14:00', 'ends_at' => '2026-10-03T22:00', 'breaks' => []],
            ],
        ])->assertSessionHasNoErrors();
        $shift = Shift::sole();
        $this->assertSame(1, $shift->assignments()->where('team_engagement_id', $first->id)->sole()->breaks()->count());
        $this->assertSame(0, $shift->assignments()->where('team_engagement_id', $later->id)->sole()->breaks()->count());
        $this->assertDatabaseCount('shift_breaks', 0);
    }

    private function payload(array $breaks = []): array
    {
        return ['name' => 'Show run', 'location_id' => $this->location->id, 'starts_at' => '2026-10-03T14:00', 'ends_at' => '2026-10-03T22:00',
            'slots' => [['role_id' => $this->role->id, 'needed' => 1]], 'breaks' => $breaks];
    }

    private function updatePayload(array $breaks = []): array
    {
        $data = $this->payload($breaks);
        unset($data['slots']);

        return $data;
    }

    private function breakRow(string $start = '2026-10-03T15:30', int $duration = 15): array
    {
        return ['duration_minutes' => $duration, 'starts_at' => $start];
    }

    private function shift(array $breaks = []): Shift
    {
        return app(ShiftService::class)->create($this->event, $this->payload($breaks));
    }

    private function updateUrl(Shift $shift): string
    {
        return route('team.shifts.update', [$this->event, $shift]);
    }

    private function rosterUrl(string $day = '2026-10-03'): string
    {
        return route('team.scheduling.roster', ['date' => $day, 'location_id' => $this->location->id]);
    }

    private function assign(Shift $shift, string $name, string $start, string $end)
    {
        $member = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => Person::create(['name' => $name, 'email' => Str::uuid().'@example.test'])->id,
            'role_id' => $this->role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);

        return app(ShiftAssignmentService::class)->create($shift, ['team_engagement_id' => $member->id, 'shift_role_slot_id' => $shift->roleSlots()->sole()->id,
            'hours_mode' => 'custom', 'starts_at' => $start, 'ends_at' => $end]);
    }
}
