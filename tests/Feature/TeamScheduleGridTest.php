<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Location;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Repositories\ShiftRepository;
use App\Services\ShiftService;
use App\Support\LabelColors;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamScheduleGridTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->makeEvent('Festival');
        $this->location = $this->event->locations()->create(['name' => 'Main stage']);
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_grid_includes_empty_locations_and_only_shifts_overlapping_the_selected_day(): void
    {
        $empty = $this->event->locations()->create(['name' => 'Empty location']);
        $ordinary = $this->shift();
        $overnight = $this->shift(['name' => 'Overnight', 'starts_at' => '2026-09-25T23:00', 'ends_at' => '2026-09-26T02:00']);
        $this->shift(['starts_at' => '2026-09-25T20:00', 'ends_at' => '2026-09-26T00:00']);
        $this->shift(['starts_at' => '2026-09-27T00:00', 'ends_at' => '2026-09-27T02:00']);
        $other = $this->makeEvent('Other');
        $foreign = $other->locations()->create(['name' => 'Foreign']);
        $other->shifts()->create($this->payload(['location_id' => $foreign->id]));
        $response = $this->getJson($this->gridUrl())->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('schedule.shift_count', 2)
            ->assertJsonPath('schedule.date', '2026-09-26')
            ->assertJsonPath('schedule.first_shift_minute', 0)
            ->assertJsonPath('data.0.id', $empty->id)->assertJsonCount(0, 'data.0.shifts')
            ->assertJsonPath('data.1.id', $this->location->id)->assertJsonCount(2, 'data.1.shifts')
            ->assertJsonPath('data.1.shifts.0.id', $overnight->id)
            ->assertJsonPath('data.1.shifts.0.starts_at', '2026-09-25T23:00')
            ->assertJsonPath('data.1.shifts.1.id', $ordinary->id);
        $this->assertSame(['id', 'name', 'color', 'location_id', 'starts_at', 'ends_at', 'total_needs', 'filled_count', 'assignment_count'], array_keys($response->json('data.1.shifts.0')));
    }

    public function test_counts_cap_each_requirement_and_do_not_leak_people_or_slots(): void
    {
        $role = Role::create(['name' => 'Crew']);
        $shift = $this->shift(['slots' => [['role_id' => $role->id, 'needed' => 1], ['role_id' => $role->id, 'needed' => 2]]]);
        $slot = $shift->roleSlots()->first();
        for ($index = 0; $index < 2; $index++) {
            $person = Person::create(['name' => 'Private '.$index, 'email' => 'private'.$index.'@example.test']);
            $member = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
            $shift->assignments()->create(['team_engagement_id' => $member->id, 'shift_role_slot_id' => $slot->id, 'role_id' => $role->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        }
        $this->getJson($this->gridUrl())->assertOk()
            ->assertJsonPath('data.0.shifts.0.total_needs', 3)
            ->assertJsonPath('data.0.shifts.0.filled_count', 1)
            ->assertJsonPath('data.0.shifts.0.assignment_count', 2)
            ->assertJsonMissingPath('data.0.shifts.0.slots')
            ->assertJsonMissingPath('data.0.shifts.0.assignments')
            ->assertDontSee('private0@example.test', false)->assertDontSee('Private 0', false);
    }

    public function test_location_rows_paginate_on_the_server_without_loading_people(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $location = $this->event->locations()->create(['name' => sprintf('Stage %02d', $index)]);
            $this->shift(['location_id' => $location->id]);
        }
        $first = $this->getJson($this->gridUrl())->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 31)->assertJsonPath('schedule.shift_count', 30);
        $second = $this->getJson($this->gridUrl(['page' => 2]))->assertOk()->assertJsonCount(6, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        DB::enableQueryLog();
        app(ShiftRepository::class)->schedule($this->event, '2026-09-26');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(8, count($queries));
        $this->assertFalse(collect($queries)->contains(fn ($query) => str_contains($query['query'], 'people') || str_contains($query['query'], 'users')));
    }

    public function test_empty_day_has_rows_and_an_explicit_zero_total(): void
    {
        $this->getJson($this->gridUrl())->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(0, 'data.0.shifts')->assertJsonPath('schedule.shift_count', 0)->assertJsonPath('schedule.first_shift_minute', null);
    }

    public function test_first_shift_time_includes_shifts_on_later_location_pages(): void
    {
        for ($index = 0; $index < 25; $index++) {
            $this->event->locations()->create(['name' => sprintf('A location %02d', $index)]);
        }
        $laterLocation = $this->event->locations()->create(['name' => 'Z location']);
        $this->shift(['location_id' => $laterLocation->id, 'starts_at' => '2026-09-26T09:15', 'ends_at' => '2026-09-26T11:00']);

        $this->getJson($this->gridUrl())->assertOk()
            ->assertJsonPath('schedule.shift_count', 1)
            ->assertJsonPath('schedule.first_shift_minute', 555)
            ->assertJsonCount(25, 'data');
    }

    public function test_standard_palette_colors_persist_on_create_and_update_and_reach_the_grid(): void
    {
        $this->get(route('team.shifts.create'))->assertInertia(fn (Assert $page) => $page->where('labelColors', LabelColors::ALL));
        foreach (LabelColors::ALL as $color) {
            $name = 'Shift '.$color;
            $this->post(route('team.shifts.store', $this->event), $this->payload(['name' => $name, 'color' => $color]))->assertRedirect();
            $this->assertDatabaseHas('shifts', ['name' => $name, 'event_id' => $this->event->id, 'color' => $color]);
        }
        $shift = Shift::where('name', 'Shift teal')->firstOrFail();
        $this->put(route('team.shifts.update', [$this->event, $shift]), $this->payload(['color' => 'violet']))->assertRedirect();
        $this->get(route('team.shifts.show', $shift))->assertInertia(fn (Assert $page) => $page->where('shift.color', 'violet')->where('labelColors', LabelColors::ALL));
        $this->getJson($this->gridUrl())->assertJsonPath('data.0.shifts.0.color', 'violet');
        $this->put(route('team.shifts.update', [$this->event, $shift]), $this->payload())->assertRedirect();
        $this->assertSame('violet', $shift->fresh()->color);
        $this->assertSame('teal', $this->shift()->fresh()->color);
    }

    public function test_color_validation_and_write_gates_protect_existing_colors(): void
    {
        $shift = $this->shift(['color' => 'rose']);
        foreach (['red', '#ffffff', null, ['teal']] as $color) {
            $this->postJson(route('team.shifts.store', $this->event), $this->payload(['color' => $color]))->assertUnprocessable()->assertJsonValidationErrors('color');
            $this->putJson(route('team.shifts.update', [$this->event, $shift]), $this->payload(['color' => $color]))->assertUnprocessable()->assertJsonValidationErrors('color');
        }
        $other = $this->makeEvent('Other');
        $this->put(route('team.shifts.update', [$other, $shift]), $this->payload(['color' => 'sky']))->assertNotFound();
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->put(route('team.shifts.update', [$this->event, $shift]), $this->payload(['color' => 'sky']))->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->event->lock();
        $this->put(route('team.shifts.update', [$this->event, $shift]), $this->payload(['color' => 'sky']))->assertForbidden();
        $this->assertSame('rose', $shift->fresh()->color);
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_view_permission_is_separate_from_team_and_edit_permission(): void
    {
        $this->grantRoleAccess($this->user, ['team.edit']);
        $this->getJson($this->gridUrl())->assertForbidden();
        $this->get(route('team.scheduling'))->assertForbidden();
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->getJson($this->gridUrl())->assertOk();
        $this->get(route('team.scheduling'))->assertInertia(fn (Assert $page) => $page->where('canManage', false)->missing('roles')->missing('candidates'));
        $this->get(route('team.shifts.create'))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->payload())->assertForbidden();
    }

    public function test_locked_admin_can_view_but_cannot_create_from_a_drag(): void
    {
        $this->event->lock();
        $this->getJson($this->gridUrl())->assertOk();
        $this->get(route('team.shifts.create', ['location_id' => $this->location->id]))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->payload(['return_tab' => 'schedule']))->assertForbidden();
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_page_exposes_event_local_date_context_and_validates_date_and_paging(): void
    {
        $this->get(route('team.scheduling', ['date' => '2026-09-27']))->assertInertia(fn (Assert $page) => $page
            ->where('scheduleDate', '2026-09-27')->where('event.timezone', 'America/Vancouver')->where('event.starts_on', '2026-09-25')->where('event.ends_on', '2026-09-27'));
        foreach ([['date' => '2026-02-30'], ['date' => 'invalid'], ['page' => -1], ['page' => 1.5], ['page' => 100001]] as $invalid) {
            $this->getJson($this->gridUrl($invalid))->assertUnprocessable();
        }
        $this->getJson(route('team.scheduling.grid'))->assertUnprocessable();
        $this->getJson(route('team.scheduling', ['date' => 'invalid']))->assertUnprocessable();
    }

    public function test_drag_prefills_the_existing_full_create_page_and_header_does_not(): void
    {
        $params = ['location_id' => $this->location->id, 'starts_at' => '2026-09-26T14:00', 'ends_at' => '2026-09-26T18:00', 'schedule_date' => '2026-09-26', 'return_tab' => 'schedule'];
        $this->get(route('team.shifts.create', $params))->assertInertia(fn (Assert $page) => $page->component('Team/CreateShift')
            ->where('prefill.location_id', $this->location->id)->where('prefill.starts_at', $params['starts_at'])->where('prefill.ends_at', $params['ends_at'])
            ->where('returnContext.return_tab', 'schedule')->where('returnContext.schedule_date', '2026-09-26'));
        $this->get(route('team.shifts.create', ['return_tab' => 'schedule', 'schedule_date' => '2026-09-26']))->assertInertia(fn (Assert $page) => $page->has('prefill', 0));
    }

    public function test_prefill_rejects_foreign_locations_and_invalid_or_reversed_hours(): void
    {
        $other = $this->makeEvent('Other');
        $foreign = $other->locations()->create(['name' => 'Foreign']);
        foreach ([['location_id' => $foreign->id], ['starts_at' => '2026-09-26T14:00'], ['starts_at' => '2026-09-26T14:00', 'ends_at' => '2026-09-26T13:00'], ['return_tab' => 'https://example.test']] as $invalid) {
            $this->getJson(route('team.shifts.create', $invalid))->assertUnprocessable();
        }
    }

    public function test_saving_from_the_schedule_returns_to_the_created_shifts_day(): void
    {
        $this->post(route('team.shifts.store', $this->event), $this->payload(['return_tab' => 'schedule']))->assertRedirect(route('team.scheduling', ['tab' => 'schedule', 'date' => '2026-09-26']));
        $this->getJson($this->gridUrl())->assertJsonPath('schedule.shift_count', 1)->assertJsonPath('data.0.shifts.0.name', 'Show run');
    }

    public function test_store_retains_form_request_scope_and_validation_with_schedule_context(): void
    {
        $other = $this->makeEvent('Other');
        $foreign = $other->locations()->create(['name' => 'Foreign']);
        foreach ([['location_id' => $foreign->id], ['name' => ''], ['ends_at' => '2026-09-26T13:00'], ['return_tab' => 'https://example.test']] as $invalid) {
            $this->postJson(route('team.shifts.store', $this->event), $this->payload(['return_tab' => 'schedule', ...$invalid]))->assertUnprocessable();
        }
        $this->post(route('team.shifts.store', $other), $this->payload(['location_id' => $foreign->id, 'return_tab' => 'schedule']))->assertNotFound();
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_shift_actions_return_to_the_originating_tab_and_day_even_when_shift_dates_change(): void
    {
        foreach (['schedule', 'list'] as $tab) {
            $context = ['return_tab' => $tab, 'schedule_date' => '2026-09-25'];
            $returnUrl = route('team.scheduling', ['tab' => $tab, 'date' => '2026-09-25']);
            $this->post(route('team.shifts.store', $this->event), $this->payload(['name' => $tab, ...$context]))->assertRedirect($returnUrl);
            $shift = $this->event->shifts()->where('name', $tab)->firstOrFail();
            $this->get(route('team.shifts.show', ['shift' => $shift, ...$context]))
                ->assertInertia(fn (Assert $page) => $page->where('returnContext', $context));
            $this->put(route('team.shifts.update', [$this->event, $shift]), $this->payload([
                'name' => $tab.' updated', 'starts_at' => '2026-09-27T14:00', 'ends_at' => '2026-09-27T22:00', ...$context,
            ]))->assertRedirect($returnUrl);
            $this->assertSame($tab.' updated', $shift->fresh()->name);
            $this->delete(route('team.shifts.destroy', [$this->event, $shift]), $context)->assertRedirect($returnUrl);
            $this->assertModelMissing($shift);
        }
    }

    public function test_invalid_return_context_is_rejected_before_any_shift_changes(): void
    {
        $shift = $this->shift();
        foreach ([['return_tab' => 'https://example.test'], ['schedule_date' => '2026-02-30']] as $context) {
            $this->getJson(route('team.shifts.show', ['shift' => $shift, ...$context]))->assertUnprocessable();
            $this->postJson(route('team.shifts.store', $this->event), $this->payload($context))->assertUnprocessable();
            $this->putJson(route('team.shifts.update', [$this->event, $shift]), $this->payload(['name' => 'Changed', ...$context]))->assertUnprocessable();
            $this->deleteJson(route('team.shifts.destroy', [$this->event, $shift]), $context)->assertUnprocessable();
        }
        $this->assertSame('Show run', $shift->fresh()->name);
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_schedule_accepts_days_and_shifts_before_and_after_the_event_dates(): void
    {
        foreach (['2026-09-24', '2026-09-28'] as $date) {
            $this->post(route('team.shifts.store', $this->event), $this->payload([
                'starts_at' => $date.'T08:00', 'ends_at' => $date.'T10:00',
            ]))->assertRedirect();
            $this->get(route('team.scheduling', ['date' => $date]))
                ->assertInertia(fn (Assert $page) => $page->where('scheduleDate', $date));
            $this->getJson($this->gridUrl(['date' => $date]))->assertOk()
                ->assertJsonPath('schedule.shift_count', 1)
                ->assertJsonPath('data.0.shifts.0.starts_at', $date.'T08:00');
        }
    }

    public function test_location_filter_scopes_rows_counts_and_first_shift_and_can_be_cleared(): void
    {
        $otherLocation = $this->event->locations()->create(['name' => 'Gate']);
        $empty = $this->event->locations()->create(['name' => 'Empty']);
        $this->shift(['starts_at' => '2026-09-26T08:00', 'ends_at' => '2026-09-26T10:00']);
        $shift = $this->shift(['location_id' => $otherLocation->id]);
        $this->getJson($this->gridUrl(['location_id' => $otherLocation->id]))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherLocation->id)
            ->assertJsonPath('data.0.shifts.0.id', $shift->id)->assertJsonPath('meta.total', 1)
            ->assertJsonPath('schedule.shift_count', 1)->assertJsonPath('schedule.first_shift_minute', 840);
        $this->getJson($this->gridUrl(['location_id' => $empty->id]))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('schedule.shift_count', 0)
            ->assertJsonPath('schedule.first_shift_minute', null);
        $this->getJson($this->gridUrl())->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('schedule.shift_count', 2)->assertJsonPath('schedule.first_shift_minute', 480);
        $otherEvent = $this->makeEvent('Foreign');
        $foreign = $otherEvent->locations()->create(['name' => 'Foreign']);
        foreach ([$foreign->id, 999999, 'invalid'] as $locationId) {
            $this->getJson($this->gridUrl(['location_id' => $locationId]))
                ->assertUnprocessable()->assertJsonValidationErrors('location_id');
        }
    }

    private function makeEvent(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-09-25', 'ends_on' => '2026-09-27', 'timezone' => 'America/Vancouver']);
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Show run', 'location_id' => $this->location->id, 'starts_at' => '2026-09-26T14:00', 'ends_at' => '2026-09-26T22:00', ...$overrides];
    }

    private function shift(array $overrides = []): Shift
    {
        return app(ShiftService::class)->create($this->event, $this->payload($overrides));
    }

    private function gridUrl(array $overrides = []): string
    {
        return route('team.scheduling.grid', ['date' => '2026-09-26', ...$overrides]);
    }
}
