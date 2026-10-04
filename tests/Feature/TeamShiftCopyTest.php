<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamShiftCopyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Event $event;

    private Shift $source;

    private array $members;

    private array $slots;

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
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $roles = [Role::create(['name' => 'Gate crew']), Role::create(['name' => 'Runner'])];
        $this->slots = [['role_id' => $roles[0]->id, 'needed' => 1], ['role_id' => $roles[1]->id, 'needed' => 2]];
        $this->source = app(ShiftService::class)->create($this->event, [
            'name' => 'Morning gate', 'color' => 'violet', 'location_id' => $location->id,
            'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00',
            'slots' => $this->slots, 'breaks' => [['duration_minutes' => 15, 'starts_at' => '2026-10-01T11:00']],
        ]);
        $this->members = array_map(fn ($name) => $this->member($name), ['Alpha', 'Beta', 'Gamma']);
        $savedSlots = $this->source->roleSlots()->get();
        foreach ($this->members as $index => $member) {
            app(ShiftAssignmentService::class)->create($this->source, [
                'team_engagement_id' => $member->id, 'shift_role_slot_id' => $savedSlots[$index === 1 ? 1 : 0]->id,
                'hours_mode' => $index === 1 ? 'custom' : 'full_shift',
                ...($index === 1 ? ['starts_at' => '2026-10-01T11:30', 'ends_at' => '2026-10-01T13:00'] : []),
            ]);
        }
        $this->actingAs($this->user);
    }

    public function test_copy_prefills_every_field_and_never_saves_on_open(): void
    {
        $before = $this->snapshot();
        $this->get(route('team.shifts.create', ['copy' => $this->source->id, 'return_tab' => 'schedule', 'return_date' => '2026-10-01']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Team/CreateShift')
            ->where('prefill.copy', $this->source->id)->where('prefill.name', 'Morning gate')
            ->where('prefill.color', 'violet')->where('prefill.location_id', $this->source->location_id)
            ->where('prefill.starts_at', '2026-10-01T10:00')->where('prefill.ends_at', '2026-10-01T14:00')
            ->has('prefill.slots', 2)->where('prefill.slots.0.role_id', $this->slots[0]['role_id'])
            ->where('prefill.slots.0.needed', 1)->where('prefill.slots.1.role_name', 'Runner')
            ->where('prefill.slots.1.needed', 2)->missing('prefill.slots.0.id')
            ->has('prefill.breaks', 1)->where('prefill.breaks.0.duration_minutes', 15)
            ->where('prefill.breaks.0.starts_at', '2026-10-01T11:00')->missing('prefill.breaks.0.id')
            ->has('prefill.assignments', 3)->where('prefill.assignments.0.name', 'Alpha')
            ->where('prefill.assignments.0.team_engagement_id', $this->members[0]->id)
            ->where('prefill.assignments.0.slot_index', 0)->where('prefill.assignments.0.hours_mode', 'full_shift')
            ->where('prefill.assignments.1.name', 'Beta')->where('prefill.assignments.1.slot_index', 1)
            ->where('prefill.assignments.1.hours_mode', 'custom')->where('prefill.assignments.1.starts_at', '2026-10-01T11:30')
            ->where('prefill.assignments.1.ends_at', '2026-10-01T13:00')->where('prefill.assignments.2.slot_index', 0)
            ->where('prefill.assignments.0.overlaps.0.shift_id', $this->source->id)
            ->where('prefill.assignments.0.overlaps.0.overlap_minutes', 240)
            ->where('returnContext.return_tab', 'schedule')->missing('prefill.assignments.0.email'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_people_breaks_and_slots_save_together_on_the_new_shift(): void
    {
        $before = $this->snapshot();
        $payload = $this->payload();
        $payload['name'] = 'Next day gate';
        $payload['color'] = 'teal';
        $payload['starts_at'] = '2026-10-02T12:00';
        $payload['ends_at'] = '2026-10-02T16:00';
        $payload['breaks'][0]['starts_at'] = '2026-10-02T13:00';
        $payload['assignments'][1]['starts_at'] = '2026-10-02T13:30';
        $payload['assignments'][1]['ends_at'] = '2026-10-02T15:00';
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $new = Shift::whereKeyNot($this->source->id)->sole();
        $this->assertSame('Next day gate', $new->name);
        $this->assertSame('teal', $new->color);
        $this->assertSame('2026-10-02T13:00', $new->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame($before, $this->snapshot($this->source->id));
        $this->get(route('team.shifts.show', $new))->assertInertia(fn (Assert $page) => $page
            ->where('shift.assignment_count', 3)->where('shift.extra_count', 1)->where('shift.filled_count', 2)
            ->where('shift.assignments.0.starts_at', '2026-10-02T12:00')->where('shift.assignments.0.ends_at', '2026-10-02T16:00')
            ->where('shift.assignments.1.starts_at', '2026-10-02T13:30')->where('shift.assignments.1.ends_at', '2026-10-02T15:00')
            ->where('shift.assignments.2.is_extra', true));
        $slots = $new->roleSlots()->get();
        $this->assertSame([$slots[0]->id, $slots[1]->id, $slots[0]->id], $new->assignments()->pluck('shift_role_slot_id')->all());
    }

    public function test_same_time_overlaps_and_extras_warn_and_do_not_block_the_save(): void
    {
        $before = $this->snapshot();
        $this->postJson(route('team.shifts.copy-preview', $this->event), $this->payload())
            ->assertOk()->assertJsonPath('data.overlaps.0.0.shift_id', $this->source->id)
            ->assertJsonPath('data.overlaps.1.0.overlap_minutes', 90);
        $this->assertSame($before, $this->snapshot());
        $this->post(route('team.shifts.store', $this->event), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shifts', 2);
        $this->assertDatabaseCount('shift_assignments', 6);
    }

    public function test_preview_rechecks_new_hours_and_includes_other_shifts(): void
    {
        $other = app(ShiftService::class)->create($this->event, [
            'name' => 'Later', 'location_id' => $this->source->location_id,
            'starts_at' => '2026-10-02T10:00', 'ends_at' => '2026-10-02T14:00', 'slots' => [$this->slots[0]],
        ]);
        app(ShiftAssignmentService::class)->create($other, ['team_engagement_id' => $this->members[0]->id, 'shift_role_slot_id' => $other->roleSlots()->sole()->id, 'hours_mode' => 'full_shift']);
        $payload = $this->payload();
        $payload['starts_at'] = '2026-10-02T10:00';
        $payload['ends_at'] = '2026-10-02T14:00';
        $payload['breaks'] = [];
        $payload['assignments'] = [$payload['assignments'][0]];
        $this->postJson(route('team.shifts.copy-preview', $this->event), $payload)->assertOk()
            ->assertJsonPath('data.overlaps.0.0.shift_id', $other->id)->assertJsonCount(1, 'data.overlaps.0');
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_failed_people_are_shown_in_prefill_and_rejected_inline_on_save(): void
    {
        $this->members[1]->update(['status' => 'declined']);
        $this->get(route('team.shifts.create', ['copy' => $this->source->id]))->assertInertia(fn (Assert $page) => $page
            ->where('prefill.assignments.1.error', __('team.scheduling.assignments.errors.eligible')));
        $before = $this->snapshot();
        $response = $this->postJson(route('team.shifts.store', $this->event), $this->payload())->assertUnprocessable()
            ->assertJsonValidationErrors('assignments.1.team_engagement_id');
        $this->assertSame([__('team.scheduling.assignments.errors.eligible')], $response->json('errors')['assignments.1.team_engagement_id']);
        $this->assertSame($before, $this->snapshot());
        $payload = $this->payload();
        unset($payload['assignments'][1]);
        $payload['assignments'] = array_values($payload['assignments']);
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shift_assignments', 5);
    }

    public function test_invalid_members_duplicates_slots_and_hours_cannot_write_any_part_of_a_copy(): void
    {
        $otherEvent = $this->event('Other');
        $foreign = $this->member('Foreign', $otherEvent);
        $stranger = $this->member('Not on source');
        $cases = [
            [['team_engagement_id' => $foreign->id], 'team_engagement_id'],
            [['team_engagement_id' => $stranger->id], 'team_engagement_id'],
            [['team_engagement_id' => $this->members[0]->id], 'team_engagement_id'],
            [['slot_index' => 999], 'slot_index'],
            [['hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T13:00'], 'ends_at'],
            [['hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T15:00'], 'ends_at'],
            [['hours_mode' => 'custom', 'starts_at' => '2026-10-01T13:00', 'ends_at' => '2026-10-01T12:00'], 'ends_at'],
            [['starts_at' => 'bad'], 'starts_at'],
            [['hours_mode' => 'invalid'], 'hours_mode'],
            [['role_id' => $this->slots[0]['role_id']], 'row'],
        ];
        $before = $this->snapshot();
        foreach ($cases as [$changes, $field]) {
            $payload = $this->payload();
            $payload['assignments'][1] = [...$payload['assignments'][1], ...$changes];
            $key = $field === 'row' ? 'assignments.1' : 'assignments.1.'.$field;
            $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
            $this->assertSame($before, $this->snapshot());
            $this->assertDatabaseCount('shifts', 1);
            $this->assertDatabaseCount('shift_assignments', 3);
            $this->assertDatabaseCount('shift_role_slots', 2);
            $this->assertDatabaseCount('shift_breaks', 1);
        }
        $payload = $this->payload();
        unset($payload['assignments'][1]['starts_at']);
        $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('assignments.1.starts_at');
    }

    public function test_removed_slots_leave_copied_people_as_detached_extras_with_their_original_roles(): void
    {
        $payload = $this->payload();
        $payload['slots'] = [];
        foreach ($payload['assignments'] as &$row) {
            $row['slot_index'] = null;
        } unset($row);
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $new = Shift::whereKeyNot($this->source->id)->sole();
        $this->assertSame([null, null, null], $new->assignments()->pluck('shift_role_slot_id')->all());
        $this->assertSame([$this->slots[0]['role_id'], $this->slots[1]['role_id'], $this->slots[0]['role_id']], $new->assignments()->pluck('role_id')->all());
        $this->get(route('team.shifts.show', $new))->assertInertia(fn (Assert $page) => $page->where('shift.extra_count', 3));
    }

    public function test_copying_a_source_with_detached_extras_preserves_all_people(): void
    {
        $this->source->roleSlots()->where('role_id', $this->slots[1]['role_id'])->delete();
        $this->get(route('team.shifts.create', ['copy' => $this->source->id]))->assertInertia(fn (Assert $page) => $page
            ->has('prefill.assignments', 3)->where('prefill.assignments.1.slot_index', null)->where('prefill.assignments.1.role_name', 'Runner'));
        $payload = $this->payload();
        array_pop($payload['slots']);
        $payload['assignments'][1]['slot_index'] = null;
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
    }

    public function test_foreign_missing_and_multiple_source_ids_are_refused(): void
    {
        $other = $this->event('Other');
        $location = $other->locations()->create(['name' => 'Elsewhere']);
        $foreign = app(ShiftService::class)->create($other, ['name' => 'Foreign', 'location_id' => $location->id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00']);
        $this->get(route('team.shifts.create', ['copy' => $foreign->id]))->assertNotFound();
        $this->get(route('team.shifts.create', ['copy' => [$this->source->id, $foreign->id]]))->assertSessionHasErrors('copy');
        foreach ([$foreign->id, null] as $copy) {
            $payload = $this->payload();
            $payload['copy'] = $copy;
            $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('copy');
            $this->postJson(route('team.shifts.copy-preview', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('copy');
        }
        $this->postJson(route('team.shifts.store', $other), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_copy_visibility_and_page_preview_and_save_require_permission_and_unlocked_event(): void
    {
        $this->get(route('team.shifts.show', $this->source))->assertInertia(fn (Assert $page) => $page->where('canManage', true)->where('event.is_locked', false));
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->actingAs($this->user->fresh());
        $this->get(route('team.shifts.show', $this->source))->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->assertCopyForbidden();
        $this->grantRoleAccess($this->user, ['scheduling.view', 'scheduling.edit']);
        $this->actingAs($this->user->fresh());
        $this->event->lock();
        $this->get(route('team.shifts.show', $this->source))->assertInertia(fn (Assert $page) => $page->where('event.is_locked', true));
        $this->assertCopyForbidden();
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_service_rechecks_eligibility_source_and_lock_and_rolls_back_all_related_writes(): void
    {
        $before = $this->snapshot();
        $this->members[1]->update(['status' => 'declined']);
        try {
            app(ShiftService::class)->create($this->event, $this->payload());
            $this->fail('Expected validation');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignments.1.team_engagement_id', $exception->errors());
        }
        $this->assertSame($before, $this->snapshot());
        $this->members[1]->update(['status' => 'hired']);
        $payload = $this->payload();
        $payload['slots'][1]['role_id'] = 999999999;
        try {
            app(ShiftService::class)->create($this->event, $payload);
            $this->fail('Expected invalid slot');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slots.1.role_id', $exception->errors());
        }
        $this->assertSame($before, $this->snapshot());
        $payload = $this->payload();
        $payload['copy'] = Shift::query()->max('id') + 1;
        try {
            app(ShiftService::class)->create($this->event, $payload);
            $this->fail('Expected invalid source');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy', $exception->errors());
        }
        $staleEvent = $this->event->fresh();
        $this->event->lock();
        try {
            app(ShiftService::class)->create($staleEvent, $this->payload());
            $this->fail('Expected locked event');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseCount('shift_assignments', 3);
    }

    public function test_invalid_breaks_are_rejected_without_saving_copied_people(): void
    {
        $payload = $this->payload();
        $payload['breaks'][0]['starts_at'] = '2026-10-01T13:55';
        $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('breaks.0.starts_at');
        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseCount('shift_assignments', 3);
    }

    private function assertCopyForbidden(): void
    {
        $this->get(route('team.shifts.create', ['copy' => $this->source->id]))->assertForbidden();
        $this->postJson(route('team.shifts.copy-preview', $this->event), $this->payload())->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->payload())->assertForbidden();
    }

    private function payload(): array
    {
        return [
            'copy' => $this->source->id, 'name' => 'Morning gate', 'color' => 'violet', 'location_id' => $this->source->location_id,
            'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', 'slots' => $this->slots,
            'breaks' => [['duration_minutes' => 15, 'starts_at' => '2026-10-01T11:00']],
            'assignments' => array_map(fn ($member, $index) => [
                'team_engagement_id' => $member->id, 'slot_index' => $index === 1 ? 1 : 0,
                'hours_mode' => $index === 1 ? 'custom' : 'full_shift',
                ...($index === 1 ? ['starts_at' => '2026-10-01T11:30', 'ends_at' => '2026-10-01T13:00'] : []),
            ], $this->members, array_keys($this->members)),
        ];
    }

    private function member(string $name, ?Event $event = null): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);

        return TeamEngagement::create(['event_id' => ($event ?? $this->event)->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02', 'timezone' => 'America/Vancouver']);
    }

    private function snapshot(?int $shiftId = null): array
    {
        $shiftId ??= $this->source->id;

        return [
            DB::table('shifts')->where('id', $shiftId)->get()->toJson(),
            DB::table('shift_role_slots')->where('shift_id', $shiftId)->orderBy('id')->get()->toJson(),
            DB::table('shift_breaks')->where('shift_id', $shiftId)->orderBy('id')->get()->toJson(),
            DB::table('shift_assignments')->where('shift_id', $shiftId)->orderBy('id')->get()->toJson(),
            // Source snapshot above stays comparable after a successful copy; failures also check counts.
        ];
    }
}
