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

    public function test_dedicated_copy_page_snapshots_every_value_without_writing(): void
    {
        $before = $this->snapshot();
        $this->get(route('team.shifts.copy', [$this->source, 'return_tab' => 'schedule']))->assertInertia(fn (Assert $page) => $page
            ->component('Team/CopyShift')->where('prefill.name', 'Morning gate')->where('prefill.color', 'violet')
            ->where('prefill.location_id', $this->source->location_id)->where('prefill.starts_at', '2026-10-01T10:00')
            ->where('prefill.ends_at', '2026-10-01T14:00')->has('prefill.slots', 2)->has('prefill.assignments', 3)->has('prefill.breaks', 1)
            ->where('prefill.assignments.1.hours_mode', 'custom')->where('prefill.assignments.1.starts_at', '2026-10-01T11:30')
            ->where('prefill.assignments.1.role_id', $this->slots[1]['role_id'])
            ->where('prefill.assignments', fn ($rows) => collect($rows)->every(fn ($row) => ! array_key_exists('overlaps', $row) && ! array_key_exists('other_shifts', $row)))
            ->where('returnContext.return_tab', 'schedule')->missing('prefill.copy')->missing('prefill.assignments.0.email'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_snapshot_still_saves_all_people_and_breaks_after_source_is_deleted(): void
    {
        $payload = $this->snapshotPayload();
        app(ShiftService::class)->delete($this->source, 3);
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $this->assertCopiedValues(Shift::sole());
    }

    public function test_snapshot_still_saves_all_original_values_after_source_is_edited(): void
    {
        $payload = $this->snapshotPayload();
        $this->source->update(['name' => 'Changed', 'color' => 'teal', 'starts_at' => '2026-10-02T10:00', 'ends_at' => '2026-10-02T14:00']);
        $this->source->assignments()->delete();
        $this->source->breaks()->delete();
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $this->assertCopiedValues(Shift::whereKeyNot($this->source->id)->sole());
        $this->assertSame('Changed', $this->source->fresh()->name);
    }

    public function test_normal_create_path_saves_moved_snapshot_atomically_and_keeps_source_unchanged(): void
    {
        $before = $this->snapshot();
        $payload = $this->snapshotPayload();
        $payload['starts_at'] = '2026-10-02T12:00';
        $payload['ends_at'] = '2026-10-02T16:00';
        $payload['breaks'][0]['starts_at'] = '2026-10-02T13:00';
        $payload['assignment_additions'][1]['starts_at'] = '2026-10-02T13:30';
        $payload['assignment_additions'][1]['ends_at'] = '2026-10-02T15:00';
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $new = Shift::whereKeyNot($this->source->id)->sole();
        $this->assertSame(3, $new->assignments()->count());
        $this->assertSame('2026-10-02T12:00', $new->assignments()->first()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame('2026-10-02T13:00', $new->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_same_time_overlaps_use_existing_create_preview_and_never_block(): void
    {
        $before = $this->snapshot();
        $this->getJson(route('team.shifts.create.assignment-overlaps', [$this->event, 'team_engagement_id' => $this->members[1]->id,
            'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:30', 'ends_at' => '2026-10-01T13:00',
            'shift_starts_at' => '2026-10-01T10:00', 'shift_ends_at' => '2026-10-01T14:00']))
            ->assertOk()->assertJsonPath('data.0.shift_id', $this->source->id)->assertJsonPath('data.0.overlap_minutes', 90);
        $this->assertSame($before, $this->snapshot());
        $this->post(route('team.shifts.store', $this->event), $this->snapshotPayload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shift_assignments', 6);
    }

    public function test_copy_creation_opens_the_new_shift_with_its_scheduling_return_context(): void
    {
        foreach ([[], ['return_tab' => 'schedule'], ['return_tab' => 'list']] as $tab) {
            $context = [...$tab, 'schedule_date' => '2026-10-01', 'schedule_location_id' => $this->source->location_id, 'schedule_view' => 'location_shifts'];
            $response = $this->post(route('team.shifts.store', $this->event), [...$this->snapshotPayload(), ...$context]);
            $created = Shift::whereKeyNot($this->source->id)->latest('id')->firstOrFail();
            $response->assertSessionHasNoErrors()->assertRedirect(route('team.shifts.show', ['shift' => $created, ...$context]))
                ->assertSessionHas('success', __('team.scheduling.toast.created'));
            $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
                ->component('Team/Shift')->where('returnContext', array_map('strval', $context))
                ->where('shift.assignments.0.overlaps.0.shift_id', $this->source->id));
        }
    }

    public function test_normal_new_shift_keeps_its_existing_redirects(): void
    {
        $payload = $this->snapshotPayload();
        unset($payload['open_created_shift']);
        foreach (['schedule', 'list'] as $tab) {
            $this->post(route('team.shifts.store', $this->event), [...$payload, 'return_tab' => $tab, 'schedule_date' => '2026-10-02'])
                ->assertSessionHasNoErrors()->assertRedirect(route('team.scheduling', ['tab' => $tab, 'date' => '2026-10-02']));
        }
        $this->post(route('team.shifts.store', $this->event), [...$payload, 'open_created_shift' => false, 'return_tab' => 'list'])
            ->assertRedirect(route('team.scheduling', ['tab' => 'list', 'date' => '2026-10-01']));
        $response = $this->post(route('team.shifts.store', $this->event), [...$payload, 'schedule_date' => '2026-10-02'])->assertSessionHasNoErrors();
        $response->assertRedirect(route('team.shifts.show', Shift::whereKeyNot($this->source->id)->latest('id')->firstOrFail()));
    }

    public function test_created_shift_redirect_flag_is_validated_without_partial_writes(): void
    {
        $before = $this->snapshot();
        $this->postJson(route('team.shifts.store', $this->event), [...$this->snapshotPayload(), 'open_created_shift' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('open_created_shift');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_only_normal_assignment_checks_apply_and_people_need_not_remain_on_source(): void
    {
        $payload = $this->snapshotPayload();
        $stranger = $this->member('Not on original');
        $payload['assignment_additions'][1]['team_engagement_id'] = $stranger->id;
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shift_assignments', ['team_engagement_id' => $stranger->id]);
    }

    public function test_foreign_ineligible_duplicate_people_invalid_slots_roles_and_hours_roll_back(): void
    {
        $payload = $this->snapshotPayload();
        $foreign = $this->member('Foreign', $this->event('Other'));
        $ineligible = $this->member('Declined');
        $ineligible->update(['status' => 'declined']);
        foreach ([
            [['team_engagement_id' => $foreign->id], 'team_engagement_id'],
            [['team_engagement_id' => $ineligible->id], 'team_engagement_id'],
            [['team_engagement_id' => $this->members[0]->id], 'team_engagement_id'],
            [['slot_key' => 'draft-999'], 'slot_key'],
            [['hours_mode' => 'custom', 'starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T13:00'], 'ends_at'],
            [['hours_mode' => 'invalid'], 'hours_mode'],
        ] as [$changes, $field]) {
            $bad = $payload;
            $bad['assignment_additions'][1] = [...$bad['assignment_additions'][1], ...$changes];
            $this->postJson(route('team.shifts.store', $this->event), $bad)->assertUnprocessable()->assertJsonValidationErrors('assignment_additions.1.'.$field);
            $this->assertDatabaseCount('shifts', 1);
            $this->assertDatabaseCount('shift_assignments', 3);
        }
        unset($payload['assignment_additions'][1]['slot_key']);
        $payload['assignment_additions'][1]['role_id'] = 999999999;
        $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('assignment_additions.1.role_id');
    }

    public function test_detached_extras_keep_snapshot_role_and_save_after_source_is_deleted(): void
    {
        $this->source->roleSlots()->where('role_id', $this->slots[1]['role_id'])->delete();
        $payload = $this->snapshotPayload();
        $this->assertArrayHasKey('role_id', $payload['assignment_additions'][1]);
        app(ShiftService::class)->delete($this->source, 3);
        $this->post(route('team.shifts.store', $this->event), $payload)->assertSessionHasNoErrors();
        $extra = Shift::sole()->assignments()->where('team_engagement_id', $this->members[1]->id)->sole();
        $this->assertNull($extra->shift_role_slot_id);
        $this->assertSame($this->slots[1]['role_id'], $extra->role_id);
    }

    public function test_copy_page_wrong_event_missing_source_permission_and_lock_gates(): void
    {
        $payload = $this->snapshotPayload();
        $other = $this->event('Other');
        $foreign = Shift::create(['event_id' => $other->id, 'location_id' => $other->locations()->create(['name' => 'Other'])->id,
            'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00']);
        $this->get(route('team.shifts.copy', $foreign))->assertNotFound();
        $this->get('/team/shifts/999999999/copy')->assertNotFound();
        $this->post(route('team.shifts.store', $other), $payload)->assertNotFound();
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->actingAs($this->user->fresh());
        $this->get(route('team.shifts.copy', $this->source))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $payload)->assertForbidden();
        $this->grantRoleAccess($this->user, ['scheduling.view', 'scheduling.edit']);
        $this->actingAs($this->user->fresh());
        $this->event->lock();
        $this->get(route('team.shifts.copy', $this->source))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $payload)->assertForbidden();
    }

    public function test_service_revalidates_people_roles_and_lock_without_partial_writes(): void
    {
        $payload = $this->snapshotPayload();
        $this->members[1]->update(['status' => 'declined']);
        try {
            app(ShiftService::class)->create($this->event, $payload);
            $this->fail('Expected validation');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment_additions.1.team_engagement_id', $exception->errors());
        }
        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseCount('shift_role_slots', 2);
        $this->assertDatabaseCount('shift_assignments', 3);
        $stale = $this->event->fresh();
        $this->event->lock();
        try {
            app(ShiftService::class)->create($stale, $payload);
            $this->fail('Expected locked event');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_invalid_break_does_not_save_any_copied_people(): void
    {
        $payload = $this->snapshotPayload();
        $payload['breaks'][0]['starts_at'] = '2026-10-01T13:55';
        $this->postJson(route('team.shifts.store', $this->event), $payload)->assertUnprocessable()->assertJsonValidationErrors('breaks.0.starts_at');
        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseCount('shift_assignments', 3);
    }

    private function snapshotPayload(): array
    {
        $response = $this->get(route('team.shifts.copy', $this->source))->assertOk();
        $draft = $response->viewData('page')['props']['prefill'];
        $slots = array_map(fn ($slot, $index) => ['role_id' => $slot['role_id'], 'needed' => $slot['needed'], 'client_key' => 'draft-'.($index + 1)], $draft['slots'], array_keys($draft['slots']));

        return [
            'open_created_shift' => true,
            ...array_intersect_key($draft, array_flip(['name', 'color', 'location_id', 'starts_at', 'ends_at', 'breaks'])),
            'slots' => $slots,
            'assignment_additions' => array_map(fn ($person) => [
                'team_engagement_id' => $person['team_engagement_id'],
                ...($person['slot_index'] === null ? ['role_id' => $person['role_id']] : ['slot_key' => $slots[$person['slot_index']]['client_key']]),
                'hours_mode' => $person['hours_mode'],
                ...($person['hours_mode'] === 'custom' ? ['starts_at' => $person['starts_at'], 'ends_at' => $person['ends_at']] : []),
            ], $draft['assignments']),
        ];
    }

    private function assertCopiedValues(Shift $shift): void
    {
        $this->assertSame('Morning gate', $shift->name);
        $this->assertSame('violet', $shift->color);
        $this->assertSame('2026-10-01T10:00', $shift->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame(3, $shift->assignments()->count());
        $this->assertSame(2, $shift->roleSlots()->count());
        $this->assertSame('2026-10-01T11:00', $shift->breaks()->sole()->starts_at->format('Y-m-d\TH:i'));
        $this->assertSame('2026-10-01T11:30', $shift->assignments()->where('team_engagement_id', $this->members[1]->id)->sole()->starts_at->format('Y-m-d\TH:i'));
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
