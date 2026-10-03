<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Location;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->put($this->updateUrl($shift), $this->payload([
            ['id' => $first->id, ...$this->breakRow('2026-10-03T21:00', 60)],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(60, $first->fresh()->duration_minutes);
        $this->put($this->updateUrl($shift), $this->payload([
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
            ->where('shift.assignments.0.scheduled_minutes', 435)->where('shift.assignments.1.scheduled_minutes', 195)
            ->where('shift.assignments.2.scheduled_minutes', 0)->where('shift.assignments.3.scheduled_minutes', 75)
            ->where('shift.assignments.3.is_extra', true)->where('shift.assignment_count', 4));
        $this->getJson($this->rosterUrl())->assertOk()->assertJsonPath('data.0.assignments.0.scheduled_minutes', 435)
            ->assertJsonPath('data.0.assignments.1.scheduled_minutes', 195)->assertJsonPath('data.0.assignments.2.scheduled_minutes', 0)
            ->assertJsonPath('data.0.assignments.3.scheduled_minutes', 75)->assertJsonMissingPath('data.0.breaks');
        $first = $shift->breaks()->first();
        $this->put($this->updateUrl($shift), $this->payload([['id' => $first->id, ...$this->breakRow(duration: 30)]]))->assertSessionHasNoErrors();
        $this->getJson($this->rosterUrl())->assertJsonPath('data.0.assignments.0.scheduled_minutes', 450);
        $this->put($this->updateUrl($shift), $this->payload())->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page->where('shift.assignments.0.scheduled_minutes', 480));
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
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'shift_breaks')));
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

    private function payload(array $breaks = []): array
    {
        return ['name' => 'Show run', 'location_id' => $this->location->id, 'starts_at' => '2026-10-03T14:00', 'ends_at' => '2026-10-03T22:00',
            'slots' => [['role_id' => $this->role->id, 'needed' => 1]], 'breaks' => $breaks];
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

        return $shift->assignments()->create(['team_engagement_id' => $member->id, 'shift_role_slot_id' => $shift->roleSlots()->sole()->id,
            'role_id' => $this->role->id, 'starts_at' => $start, 'ends_at' => $end]);
    }
}
