<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamShiftRoleSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_create_stores_rows_without_changing_event_access_and_reads_them_in_insertion_order(): void
    {
        [$user, $event, $location] = $this->context();
        $crew = Role::query()->create(['name' => 'Crew']);
        $lead = Role::query()->create(['name' => 'Lead', 'permissions' => ['artists.edit']])->refresh();
        $this->grantRoleAccess($user, ['scheduling.edit']);
        $peopleBefore = DB::table('people')->count();
        $accessBefore = DB::table('team_engagements')->count();
        $this->actingAs($user)->post(route('team.shifts.store', $event), $this->payload($location, [
            ['role_id' => $crew->id, 'needed' => 2],
            ['role_id' => $lead->id, 'needed' => 1],
            ['role_id' => $crew->id, 'needed' => 3],
        ]))->assertSessionHasNoErrors();

        $shift = Shift::query()->sole();
        $slots = $shift->roleSlots()->get();
        $this->assertSame([$crew->id, $lead->id, $crew->id], $slots->pluck('role_id')->all());
        $this->assertSame(6, $slots->sum('needed'));
        $this->assertDatabaseCount('people', $peopleBefore);
        $this->assertDatabaseCount('team_engagements', $accessBefore);
        $this->assertEquals($lead->toArray(), $lead->fresh()->toArray());
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->has('shift.slots', 3)
            ->where('shift.slots.0.role_name', 'Crew')
            ->where('shift.slots.1.role_name', 'Lead')
            ->where('shift.total_needs', 6)
            ->where('shift.filled_count', 0));
        $this->getJson(route('team.scheduling.shifts'))->assertOk()
            ->assertJsonPath('data.0.slots.0.role_name', 'Crew')
            ->assertJsonPath('data.0.slots.1.needed', 1)
            ->assertJsonPath('data.0.slots.2.needed', 3)
            ->assertJsonPath('data.0.total_needs', 6)
            ->assertJsonPath('data.0.filled_count', 0);
    }

    public function test_edit_preserves_identity_and_order_while_adding_and_removing_rows(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [
            ['role_id' => $role->id, 'needed' => 1],
            ['role_id' => $role->id, 'needed' => 2],
        ]));
        $rows = $shift->roleSlots()->get()->sortBy('sort_order')->values();
        $first = $rows[0];
        $removed = $rows[1];
        $this->actingAs($user)->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['id' => $first->id, 'role_id' => $role->id, 'needed' => 5],
            ['role_id' => $role->id, 'needed' => 3],
        ]))->assertSessionHasNoErrors();
        $this->assertModelMissing($removed);
        $this->assertDatabaseHas('shift_role_slots', ['id' => $first->id, 'needed' => 5, 'sort_order' => 0]);
        $new = $shift->roleSlots()->whereKeyNot($first->id)->sole();
        $this->assertSame(2, $new->sort_order);
        $this->actingAs($user)->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['id' => $new->id, 'role_id' => $role->id, 'needed' => 3],
            ['id' => $first->id, 'role_id' => $role->id, 'needed' => 5],
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$first->id, $new->id], $shift->roleSlots()->pluck('id')->all());
    }

    public function test_omitted_slots_preserve_needs_and_empty_slots_remove_all(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $payload = $this->payload($location);
        unset($payload['slots']);
        $this->actingAs($user)->put(route('team.shifts.update', [$event, $shift]), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $shift->roleSlots()->sum('needed'));
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shift_role_slots', 0);
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.total_needs', 0)
            ->assertJsonPath('data.0.slots', [])->assertJsonPath('data.0.filled_count', 0);
    }

    public function test_invalid_nested_payloads_never_save_the_shift(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $valid = ['role_id' => $role->id, 'needed' => 1];
        foreach ([
            ['role_id' => null], ['role_id' => $role->id + 9999], ['needed' => null],
            ['needed' => 0], ['needed' => -1], ['needed' => 1.5], ['needed' => 'many'],
            ['needed' => 2147483648], ['id' => 999],
            ['role_name' => 'Invented'], ['sort_order' => 0], ['event_id' => $event->id],
        ] as $change) {
            $this->actingAs($user)->post(route('team.shifts.store', $event), $this->payload($location, [array_replace($valid, $change)]))
                ->assertSessionHasErrors();
            $this->assertDatabaseCount('shifts', 0);
            $this->assertDatabaseCount('shift_role_slots', 0);
        }
        $this->post(route('team.shifts.store', $event), $this->payload($location) + ['total_needs' => 5])
            ->assertSessionHasErrors('total_needs');
        $this->post(route('team.shifts.store', $event), $this->payload($location, [2 => $valid]))
            ->assertSessionHasErrors('slots');
    }

    public function test_off_roles_are_not_offered_or_newly_selected_but_saved_rows_can_be_retained(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $slot = $shift->roleSlots()->sole();
        $role->update(['active' => false]);
        $this->actingAs($user)->get(route('team.shifts.create'))->assertInertia(fn (Assert $page) => $page->has('roles', 0));
        $this->post(route('team.shifts.store', $event), $this->payload($location, [['role_id' => $role->id, 'needed' => 1]]))
            ->assertSessionHasErrors('slots.0.role_id');
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['id' => $slot->id, 'role_id' => $role->id, 'needed' => 3],
        ]))->assertSessionHasNoErrors();
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.slots.0.role_name', 'Crew')->where('shift.total_needs', 3)->has('roles', 0));
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['role_id' => $role->id, 'needed' => 3],
        ]))->assertSessionHasErrors('slots.0.role_id');
        $replacement = Role::query()->create(['name' => 'New crew']);
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['id' => $slot->id, 'role_id' => $replacement->id, 'needed' => 3],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shift_role_slots', ['id' => $slot->id, 'role_id' => $replacement->id]);
    }

    public function test_foreign_duplicate_and_stale_slot_ids_are_refused_without_partial_updates(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 1]]));
        $own = $shift->roleSlots()->sole();
        $otherShift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 1]]));
        $foreign = $otherShift->roleSlots()->sole();
        $this->actingAs($user);
        foreach ([[$foreign->id], [$own->id, $own->id], [$own->id + 9999]] as $ids) {
            $payload = $this->payload($location, array_map(fn ($id) => ['id' => $id, 'role_id' => $role->id, 'needed' => 8], $ids));
            $payload['name'] = 'Changed';
            $this->put(route('team.shifts.update', [$event, $shift]), $payload)->assertSessionHasErrors();
            $this->assertSame('Show run', $shift->fresh()->name);
            $this->assertSame(1, $own->fresh()->needed);
        }
        $own->delete();
        try {
            app(ShiftService::class)->update($shift, $this->payload($location, [['id' => $own->id, 'role_id' => $role->id, 'needed' => 2]]));
            $this->fail('The service must recheck stale references.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slots.0.id', $exception->errors());
        }
    }

    public function test_service_rechecks_role_eligibility_before_writing(): void
    {
        [, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew', 'active' => false]);
        try {
            app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 1]]));
            $this->fail('The service must refuse off roles.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slots.0.role_id', $exception->errors());
        }
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_view_only_has_slot_data_but_no_role_catalog_and_cannot_change_it(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $this->grantRoleAccess($user, ['scheduling.view']);
        $this->actingAs($user)->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)->has('roles', 0)->where('shift.total_needs', 2));
        $this->getJson(route('team.scheduling.shifts'))->assertOk()->assertJsonPath('data.0.total_needs', 2);
        $this->get(route('team.shifts.create'))->assertForbidden();
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location))->assertForbidden();
        $this->post(route('team.shifts.store', $event), $this->payload($location))->assertForbidden();
        $this->assertDatabaseCount('shift_role_slots', 1);
        $this->grantRoleAccess($user, []);
        $this->get(route('team.shifts.show', $shift))->assertForbidden();
        $this->getJson(route('team.scheduling.shifts'))->assertForbidden();
        $this->get(route('team.scheduling'))->assertForbidden();
    }

    public function test_locked_admin_cannot_create_edit_or_remove_slots(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $slot = $shift->roleSlots()->sole();
        $event->lock();
        $this->actingAs($user)->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('event.is_locked', true)->where('shift.total_needs', 2)->has('roles', 0));
        $this->post(route('team.shifts.store', $event), $this->payload($location))->assertForbidden();
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location, [
            ['id' => $slot->id, 'role_id' => $role->id, 'needed' => 10],
        ]))->assertForbidden();
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location))->assertForbidden();
        $this->delete(route('team.shifts.destroy', [$event, $shift]))->assertForbidden();
        $this->assertSame(2, $slot->fresh()->needed);
    }

    public function test_shift_delete_cascades_slots_and_keeps_the_role(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $this->actingAs($user)->delete(route('team.shifts.destroy', [$event, $shift]))->assertRedirect();
        $this->assertDatabaseCount('shift_role_slots', 0);
        $this->assertModelExists($role);
    }

    public function test_database_checks_and_role_fk_are_enforced_with_savepoints(): void
    {
        [, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $slot = $shift->roleSlots()->sole();
        foreach ([fn () => $slot->update(['needed' => 0]), fn () => $slot->update(['sort_order' => -1]), fn () => $role->forceDelete()] as $write) {
            try {
                DB::transaction($write);
                $this->fail('Database constraint must refuse the write.');
            } catch (QueryException) {
                $this->assertModelExists($role);
                $this->assertSame(2, $slot->fresh()->needed);
            }
        }
    }

    public function test_paging_eager_loads_roles_with_a_bounded_number_of_queries(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        for ($i = 0; $i < 30; $i++) {
            app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        }
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->actingAs($user)->getJson(route('team.scheduling.shifts', ['start' => 0, 'length' => 25]))
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('recordsTotal', 30);
        $roleQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'from "roles"') && str_contains($sql, ' in '));
        $slotQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'from "shift_role_slots"'));
        $this->assertCount(1, $roleQueries);
        $this->assertCount(1, $slotQueries);
    }

    public function test_slots_are_event_scoped_and_unlocked_nonprimary_events_remain_writable(): void
    {
        [$user, $primary, $primaryLocation] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $primaryShift = app(ShiftService::class)->create($primary, $this->payload($primaryLocation, [['role_id' => $role->id, 'needed' => 1]]));
        $foreign = $primaryShift->roleSlots()->sole();
        $other = Event::query()->create(['name' => 'Second festival', 'starts_on' => '2026-09-25', 'ends_on' => '2026-09-27', 'timezone' => 'America/Vancouver']);
        $location = $other->locations()->create(['name' => 'Gate']);
        $user->setCurrentEvent($other);
        $this->actingAs($user)->post(route('team.shifts.store', $other), $this->payload($location->id, [['role_id' => $role->id, 'needed' => 2]]))
            ->assertSessionHasNoErrors();
        $shift = $other->shifts()->sole();
        $this->put(route('team.shifts.update', [$other, $shift]), $this->payload($location->id, [['id' => $foreign->id, 'role_id' => $role->id, 'needed' => 3]]))
            ->assertSessionHasErrors('slots.0.id');
        $this->put(route('team.shifts.update', [$other, $primaryShift]), $this->payload($location->id))->assertNotFound();
        $this->get(route('team.shifts.show', $primaryShift))->assertNotFound();
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.id', $shift->id);
        $this->assertSame(1, $foreign->fresh()->needed);
        $this->assertSame($primary->id, app(OrganizationContext::class)->organization()->active_event_id);
    }

    public function test_locked_nonadmin_editor_is_read_only_too(): void
    {
        [$user, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew', 'permissions' => ['scheduling.edit']]);
        $user->forceFill(['is_admin' => false])->save();
        TeamEngagement::query()->create([
            'event_id' => $event->id, 'person_id' => $user->person_id, 'role_id' => $role->id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [['role_id' => $role->id, 'needed' => 2]]));
        $event->lock();
        $this->actingAs($user)->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page
            ->where('event.is_locked', true)->where('shift.total_needs', 2)->has('roles', 0));
        $this->put(route('team.shifts.update', [$event, $shift]), $this->payload($location))->assertForbidden();
        $this->post(route('team.shifts.store', $event), $this->payload($location))->assertForbidden();
        $this->assertSame(2, $shift->roleSlots()->sum('needed'));
    }

    public function test_fresh_role_slots_preserve_counts_and_order_without_the_legacy_field(): void
    {
        [, $event, $location] = $this->context();
        $role = Role::query()->create(['name' => 'Crew']);
        $shift = app(ShiftService::class)->create($event, $this->payload($location, [
            ['role_id' => $role->id, 'needed' => 2],
            ['role_id' => $role->id, 'needed' => 3],
        ]));
        $slots = $shift->roleSlots()->get();

        $this->assertFalse(Schema::hasColumn('shift_role_slots', 'is_supervisor'));
        $this->assertSame([2, 3], $slots->pluck('needed')->all());
        $this->assertSame([0, 1], $slots->pluck('sort_order')->all());
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $event = Event::query()->create(['name' => 'Festival', 'starts_on' => '2026-09-25', 'ends_on' => '2026-09-27', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $user->setCurrentEvent($event);
        $location = $event->locations()->create(['name' => 'Main stage']);

        return [$user, $event, $location->id];
    }

    private function payload(int $location, array $slots = []): array
    {
        return ['name' => 'Show run', 'location_id' => $location, 'starts_at' => '2026-09-26T14:00', 'ends_at' => '2026-09-26T22:00', 'slots' => $slots];
    }
}
