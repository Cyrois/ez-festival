<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Shift;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamShiftsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_schedule_datatable_only_returns_the_current_events_shifts(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $currentShift = $event->shifts()->create([
            'location_id' => $location->id,
            'name' => 'Show run',
            'starts_at' => '2026-09-26 14:00:00',
            'ends_at' => '2026-09-26 22:00:00',
        ]);

        $otherEvent = $this->event('Other festival');
        $otherLocation = $otherEvent->locations()->create(['name' => 'Other stage']);
        $otherEvent->shifts()->create([
            'location_id' => $otherLocation->id,
            'name' => 'Other shift',
            'starts_at' => '2026-09-26 10:00:00',
            'ends_at' => '2026-09-26 12:00:00',
        ]);

        $this->actingAs($user)
            ->getJson(route('team.scheduling.shifts', [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
            ]))
            ->assertOk()
            ->assertJsonPath('draw', 1)
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentShift->id)
            ->assertJsonPath('data.0.name', 'Show run')
            ->assertJsonPath('data.0.location', 'Main stage')
            ->assertJsonPath('data.0.starts_at', '2026-09-26T14:00')
            ->assertJsonPath('data.0.ends_at', '2026-09-26T22:00');
    }

    public function test_schedule_datatable_searches_and_paginates_on_the_server(): void
    {
        [$user, $event] = $this->eventContext();
        $mainStage = $event->locations()->create(['name' => 'Main stage']);
        $gate = $event->locations()->create(['name' => 'Gate']);
        $event->shifts()->create($this->shiftPayload($mainStage->id));
        $matchingShift = $event->shifts()->create($this->shiftPayload($gate->id, [
            'name' => 'Gate PM',
        ]));

        $this->actingAs($user)
            ->getJson(route('team.scheduling.shifts', [
                'draw' => 2,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'gate'],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]))
            ->assertOk()
            ->assertJsonPath('draw', 2)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingShift->id);
    }

    public function test_schedule_datatable_search_treats_like_wildcards_literally(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $event->shifts()->create($this->shiftPayload($location->id, ['name' => 'Gate AM']));
        $percentShift = $event->shifts()->create($this->shiftPayload($location->id, ['name' => '100% crew']));
        $underscoreShift = $event->shifts()->create($this->shiftPayload($location->id, ['name' => 'load_in']));
        $backslashShift = $event->shifts()->create($this->shiftPayload($location->id, ['name' => 'Bar \\ back']));

        foreach (['%' => $percentShift, '_' => $underscoreShift, '\\' => $backslashShift] as $term => $shift) {
            $this->actingAs($user)
                ->getJson(route('team.scheduling.shifts', [
                    'draw' => 1,
                    'search' => ['value' => $term],
                ]))
                ->assertOk()
                ->assertJsonPath('recordsTotal', 4)
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.id', $shift->id);
        }
    }

    public function test_user_can_create_a_shift(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        $response = $this->actingAs($user)->post(
            route('team.shifts.store', $event),
            $this->shiftPayload($location->id),
        );

        $shift = Shift::query()->sole();

        $response->assertRedirect(route('team.shifts.show', $shift));
        $this->assertDatabaseHas('shifts', [
            'event_id' => $event->id,
            'location_id' => $location->id,
            'name' => 'Show run',
            'starts_at' => '2026-09-26 14:00:00',
            'ends_at' => '2026-09-26 22:00:00',
        ]);
    }

    public function test_scheduling_supplies_the_create_popup(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        $this->actingAs($user)
            ->get(route('team.scheduling'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Scheduling')
                ->where('event.id', $event->id)
                ->where('event.name', 'Sunrise Folk Fest 2026')
                ->has('locations', 1)
                ->where('locations.0.id', $location->id)
                ->where('locations.0.name', 'Main stage'));
    }

    public function test_locked_event_does_not_supply_create_role_options(): void
    {
        [$user, $event] = $this->eventContext();
        $event->lock();

        $this->actingAs($user)
            ->get(route('team.scheduling'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.is_locked', true)
                ->has('roles', 0));
    }

    public function test_user_can_create_a_shift_without_a_name(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Headquarters']);

        $this->actingAs($user)
            ->post(
                route('team.shifts.store', $event),
                $this->shiftPayload($location->id, ['name' => '']),
            )
            ->assertRedirect();

        $this->assertNull(Shift::query()->sole()->name);
    }

    public function test_required_shift_fields_are_validated(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        foreach (['location_id', 'starts_at', 'ends_at'] as $field) {
            $payload = $this->shiftPayload($location->id);
            unset($payload[$field]);

            $this->actingAs($user)
                ->from(route('team.scheduling'))
                ->post(route('team.shifts.store', $event), $payload)
                ->assertRedirect(route('team.scheduling'))
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_shift_end_must_be_after_its_start(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        $this->actingAs($user)
            ->post(route('team.shifts.store', $event), $this->shiftPayload($location->id, [
                'ends_at' => '2026-09-26T13:59',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_location_must_belong_to_the_shift_event(): void
    {
        [$user, $event] = $this->eventContext();
        $otherEvent = $this->event('Other festival');
        $foreignLocation = $otherEvent->locations()->create(['name' => 'Other stage']);

        $this->actingAs($user)
            ->post(
                route('team.shifts.store', $event),
                $this->shiftPayload($foreignLocation->id),
            )
            ->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_user_can_view_and_update_a_shift(): void
    {
        [$user, $event] = $this->eventContext();
        $mainStage = $event->locations()->create(['name' => 'Main stage']);
        $gate = $event->locations()->create(['name' => 'Gate']);
        $shift = $event->shifts()->create($this->shiftPayload($mainStage->id));

        $this->actingAs($user)
            ->get(route('team.shifts.show', $shift))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Shift')
                ->where('shift.id', $shift->id)
                ->where('shift.name', 'Show run')
                ->has('locations', 2));

        $this->actingAs($user)
            ->put(
                route('team.shifts.update', [$event, $shift]),
                $this->shiftPayload($gate->id, [
                    'name' => 'Gate PM',
                    'starts_at' => '2026-09-27T16:00',
                    'ends_at' => '2026-09-27T23:00',
                ]),
            )
            ->assertRedirect(route('team.shifts.show', $shift));

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'location_id' => $gate->id,
            'name' => 'Gate PM',
            'starts_at' => '2026-09-27 16:00:00',
            'ends_at' => '2026-09-27 23:00:00',
        ]);
    }

    public function test_user_can_delete_a_shift(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $shift = $event->shifts()->create($this->shiftPayload($location->id));

        $this->actingAs($user)
            ->delete(route('team.shifts.destroy', [$event, $shift]))
            ->assertRedirect(route('team.scheduling', ['tab' => 'list']));

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_shift_routes_reject_another_event(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $shift = $event->shifts()->create($this->shiftPayload($location->id));
        $otherEvent = $this->event('Other festival');
        $otherLocation = $otherEvent->locations()->create(['name' => 'Other stage']);

        $this->actingAs($user)
            ->get(route('team.shifts.show', $otherEvent->shifts()->create(
                $this->shiftPayload($otherLocation->id),
            )))
            ->assertNotFound();

        $this->actingAs($user)
            ->put(
                route('team.shifts.update', [$otherEvent, $shift]),
                $this->shiftPayload($otherLocation->id),
            )
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('team.shifts.destroy', [$otherEvent, $shift]))
            ->assertNotFound();
    }

    public function test_locked_event_blocks_shift_writes(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $event->lock();

        $this->actingAs($user)
            ->post(
                route('team.shifts.store', $event),
                $this->shiftPayload($location->id),
            )
            ->assertForbidden();

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_locked_event_blocks_shift_updates_and_deletes(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $shift = $event->shifts()->create($this->shiftPayload($location->id));
        $event->lock();

        $this->actingAs($user)
            ->put(
                route('team.shifts.update', [$event, $shift]),
                $this->shiftPayload($location->id, ['name' => 'Changed']),
            )
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('team.shifts.destroy', [$event, $shift]))
            ->assertForbidden();

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Show run']);
    }

    public function test_shift_writes_require_the_edit_scheduling_permission(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $this->grantRoleAccess($user);
        Gate::define('scheduling.edit', fn (): bool => false);

        $this->actingAs($user)
            ->post(
                route('team.shifts.store', $event),
                $this->shiftPayload($location->id),
            )
            ->assertForbidden();

        $this->assertDatabaseCount('shifts', 0);
    }

    /** @return array{User, Event} */
    private function eventContext(): array
    {
        $user = User::factory()->create();
        $event = $this->event('Sunrise Folk Fest 2026');
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);
        $this->grantAdminAccess($user);

        return [$user, $event];
    }

    private function event(string $name): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2026-09-25',
            'ends_on' => '2026-09-27',
            'timezone' => 'America/Vancouver',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function shiftPayload(int $locationId, array $overrides = []): array
    {
        return array_merge([
            'location_id' => $locationId,
            'name' => 'Show run',
            'starts_at' => '2026-09-26T14:00',
            'ends_at' => '2026-09-26T22:00',
        ], $overrides);
    }
}
