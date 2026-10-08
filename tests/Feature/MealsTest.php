<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealClaim;
use App\Models\User;
use App\Services\EventService;
use App\Services\MealService;
use App\Services\MealTypeService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MealsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Meals festival');
        $this->user = User::factory()->create();
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->grantRoleAccess($this->user, ['meals.edit']);
        $this->actingAs($this->user);
    }

    public function test_list_is_scoped_paginated_and_sorted_by_date_window_then_id(): void
    {
        $later = $this->meal(['name' => 'Sat Dinner', 'date' => '2027-07-11']);
        $first = $this->meal(['name' => 'Fri Lunch', 'starts_at' => '12:30', 'ends_at' => '14:00']);
        $second = $this->meal(['name' => 'Fri Dinner']);
        $this->meal(['name' => 'Foreign meal'], $this->event('Other'));

        $this->table()->assertOk()->assertJsonPath('recordsTotal', 3)->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)->assertJsonPath('data.2.id', $later->id)
            ->assertJsonPath('data.0.date', '2027-07-10')->assertJsonPath('data.0.starts_at', '12:30')
            ->assertJsonPath('data.0.meal_type.name', 'Dinner');
        $this->table(['start' => 1, 'length' => 1])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->table(['length' => -1])->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->table(['length' => 101])->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->table(['start' => -1])->assertUnprocessable()->assertJsonValidationErrors('start');
    }

    public function test_empty_settings_and_forms_expose_only_current_event_data(): void
    {
        $this->get(route('meals.settings'))->assertInertia(fn (Assert $page) => $page->where('mealCount', 0));
        $other = $this->event('Other');
        $other->mealTypes()->create(['name' => 'Foreign type', 'starts_at' => '02:00:00', 'ends_at' => '03:00:00']);
        $this->get(route('meals.create'))->assertInertia(fn (Assert $page) => $page
            ->component('Kitchen/MealEditor')->where('meal', null)->where('canWrite', true)
            ->where('event.starts_on', '2027-07-10')->where('event.ends_on', '2027-07-12')
            ->where('event.timezone', 'America/Vancouver')->has('mealTypes', 4)
            ->where('mealTypes.2.name', 'Dinner')->where('mealTypes.2.starts_at', '17:00')
            ->where('mealTypes.2.ends_at', '20:00'));
        $meal = $this->meal();
        $this->get(route('meals.edit', $meal))->assertInertia(fn (Assert $page) => $page
            ->where('meal.id', $meal->id)->where('meal.starts_at', '17:30')
            ->where('meal.ends_at', '19:30')->where('meal.date', '2027-07-10'));
    }

    public function test_create_update_and_delete_preserve_identity_and_custom_window(): void
    {
        $this->post(route('meals.store', $this->event), $this->data(['name' => ' Fri Dinner ']))
            ->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $meal = $this->event->meals()->sole();
        $this->assertSame('fri dinner', $meal->name_key);
        $this->assertSame('17:30:00', $meal->starts_at);
        $this->put(route('meals.update', [$this->event, $meal]), $this->data([
            'name' => 'Sun Late Lunch', 'date' => '2027-07-12',
            'meal_type_id' => $this->event->mealTypes()->where('name', 'Lunch')->sole()->id,
            'starts_at' => '14:00', 'ends_at' => '15:00',
        ]))->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $this->assertSame('Sun Late Lunch', $meal->fresh()->name);
        $this->assertSame('14:00:00', $meal->fresh()->starts_at);
        $this->table()->assertJsonPath('data.0.id', $meal->id)->assertJsonPath('data.0.meal_type.name', 'Lunch');
        $this->delete(route('meals.destroy', [$this->event, $meal]))->assertRedirect(route('meals.settings'));
        $this->assertModelMissing($meal);
        $this->table()->assertJsonCount(0, 'data');
    }

    public function test_same_type_and_day_can_repeat_and_names_are_unique_per_event(): void
    {
        $first = $this->meal();
        $this->post(route('meals.store', $this->event), $this->data(['name' => 'Fri Dinner (late)']))->assertSessionHasNoErrors();
        $this->post(route('meals.store', $this->event), $this->data(['name' => ' FRI DINNER ']))->assertSessionHasErrors('name');
        $this->put(route('meals.update', [$this->event, $first]), $this->data())->assertSessionHasNoErrors();
        $this->assertSame(2, $this->event->meals()->count());
        $this->assertModelExists($this->meal([], $this->event('Other')));
    }

    public function test_overnight_window_stays_on_its_start_day_including_the_last_event_day(): void
    {
        $this->post(route('meals.store', $this->event), $this->data([
            'name' => 'Sun Midnight', 'date' => '2027-07-12', 'starts_at' => '23:30', 'ends_at' => '00:30',
        ]))->assertSessionHasNoErrors();
        $this->table()->assertJsonPath('data.0.date', '2027-07-12')->assertJsonPath('data.0.ends_at', '00:30');
    }

    #[DataProvider('datesOutsideEvent')]
    public function test_meals_can_be_created_and_updated_outside_event_dates(string $date): void
    {
        $this->post(route('meals.store', $this->event), $this->data(['date' => $date]))
            ->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $created = $this->event->meals()->sole();
        $this->assertSame($date, $created->date->toDateString());
        $this->table()->assertJsonPath('data.0.date', $date);

        $existing = $this->meal(['name' => 'Existing meal']);
        $this->put(route('meals.update', [$this->event, $existing]), $this->data(['name' => $existing->name, 'date' => $date]))
            ->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $this->assertSame($date, $existing->fresh()->date->toDateString());
    }

    public static function datesOutsideEvent(): array
    {
        return [
            'before event' => ['2027-06-30'],
            'after event' => ['2027-10-07'],
        ];
    }

    public function test_type_changes_do_not_rewrite_meals_and_resource_reads_the_current_type(): void
    {
        $meal = $this->meal();
        $type = $meal->mealType;
        app(MealTypeService::class)->update($this->event, $type, [
            'name' => 'Evening', 'starts_at' => '18:00', 'ends_at' => '20:30',
        ]);
        $this->assertSame('17:30:00', $meal->fresh()->starts_at);
        $this->assertSame('19:30:00', $meal->fresh()->ends_at);
        $this->table()->assertJsonPath('data.0.meal_type.name', 'Evening');
    }

    #[DataProvider('invalidMeals')]
    public function test_form_requests_refuse_invalid_create_and_update(array $changes, string $field): void
    {
        $meal = $this->meal(['name' => 'Saved']);
        $data = $this->data($changes);
        $this->post(route('meals.store', $this->event), $data)->assertSessionHasErrors($field);
        $this->put(route('meals.update', [$this->event, $meal]), $data)->assertSessionHasErrors($field);
        $this->assertSame(1, $this->event->meals()->count());
        $this->assertSame('Saved', $meal->fresh()->name);
    }

    public static function invalidMeals(): array
    {
        return [
            'empty name' => [['name' => '   '], 'name'],
            'long name' => [['name' => str_repeat('x', 256)], 'name'],
            'missing type' => [['meal_type_id' => ''], 'meal_type_id'],
            'unknown type' => [['meal_type_id' => 999999999], 'meal_type_id'],
            'empty date' => [['date' => ''], 'date'],
            'bad date' => [['date' => '2027-02-30'], 'date'],
            'missing start' => [['starts_at' => ''], 'starts_at'],
            'missing end' => [['ends_at' => ''], 'ends_at'],
            'bad time' => [['starts_at' => '24:00'], 'starts_at'],
            'seconds' => [['ends_at' => '19:30:00'], 'ends_at'],
            'equal endpoints' => [['ends_at' => '17:30'], 'ends_at'],
        ];
    }

    public function test_three_independent_domain_errors_are_reported_together(): void
    {
        $this->meal();
        $this->post(route('meals.store', $this->event), $this->data([
            'name' => 'fri dinner', 'meal_type_id' => 999999999, 'ends_at' => '17:30',
        ]))->assertSessionHasErrors(['name', 'meal_type_id', 'ends_at']);
    }

    public function test_foreign_type_records_and_noncurrent_event_writes_are_refused(): void
    {
        $other = $this->event('Other');
        $foreign = $this->meal([], $other);
        $this->grantRoleAccess($this->user, ['meals.edit']);
        $meal = $this->meal();
        $this->post(route('meals.store', $this->event), $this->data(['meal_type_id' => $foreign->meal_type_id]))
            ->assertSessionHasErrors('meal_type_id');
        $this->put(route('meals.update', [$this->event, $meal]), $this->data(['meal_type_id' => $foreign->meal_type_id]))
            ->assertSessionHasErrors('meal_type_id');
        $this->get(route('meals.edit', $foreign))->assertNotFound();
        $this->put(route('meals.update', [$this->event, $foreign]), $this->data())->assertNotFound();
        $this->delete(route('meals.destroy', [$this->event, $foreign]))->assertNotFound();
        $this->post(route('meals.store', $other), $this->data([], $other))->assertNotFound();
        $this->put(route('meals.update', [$other, $foreign]), $this->data([], $other))->assertNotFound();
        $this->delete(route('meals.destroy', [$other, $foreign]))->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_view_only_reads_are_allowed_but_every_edit_route_is_refused_and_revocation_is_immediate(): void
    {
        $meal = $this->meal();
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->get(route('meals.settings'))->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
        $this->table()->assertOk();
        $this->get(route('meals.create'))->assertForbidden();
        $this->get(route('meals.edit', $meal))->assertForbidden();
        $this->post(route('meals.store', $this->event), $this->data())->assertForbidden();
        $this->put(route('meals.update', [$this->event, $meal]), $this->data())->assertForbidden();
        $this->delete(route('meals.destroy', [$this->event, $meal]))->assertForbidden();
        $role = $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role;
        $role->update(['permissions' => ['artists.view']]);
        $this->get(route('meals.settings'))->assertForbidden();
        $this->table()->assertForbidden();
        $this->assertModelExists($meal);
    }

    public function test_a_locked_event_refuses_every_write_including_admins(): void
    {
        $meal = $this->meal();
        $this->event->lock();
        foreach ([false, true] as $admin) {
            $this->user->forceFill(['is_admin' => $admin])->save();
            $this->get(route('meals.edit', $meal))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
            $this->table()->assertOk();
            $this->post(route('meals.store', $this->event), $this->data())->assertForbidden();
            $this->put(route('meals.update', [$this->event, $meal]), $this->data())->assertForbidden();
            $this->delete(route('meals.destroy', [$this->event, $meal]))->assertForbidden();
        }
        $this->assertModelExists($meal);
    }

    public function test_used_meal_delete_is_refused_by_the_form_request_and_service_before_the_fk(): void
    {
        $meal = $this->meal();
        $member = $this->event->teamEngagements()->firstOrFail();
        MealClaim::create([
            'event_id' => $this->event->id, 'meal_id' => $meal->id, 'team_engagement_id' => $member->id,
            'meal_type_id' => $meal->meal_type_id, 'source_shift_id' => 999999, 'meal_name' => $meal->name,
            'meal_date' => $meal->date, 'starts_at' => $meal->starts_at, 'ends_at' => $meal->ends_at,
            'shift_location_name' => 'Gate', 'shift_starts_at' => '2027-07-10 12:00', 'shift_ends_at' => '2027-07-10 22:00',
            'claimed_by' => $this->user->id, 'claimed_at' => now(),
        ]);
        $this->delete(route('meals.destroy', [$this->event, $meal]))->assertSessionHasErrors([
            'meal' => __('meals.errors.used', ['name' => $meal->name]),
        ]);
        try {
            app(MealService::class)->destroy($this->event, $meal);
            $this->fail('Expected a used-meal validation error.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('meal', $error->errors());
        }
        $this->assertModelExists($meal);
        $this->assertDatabaseCount('meal_claims', 1);
        DB::table('meal_claims')->delete();
        $this->delete(route('meals.destroy', [$this->event, $meal]))->assertSessionHasNoErrors();
        $this->assertModelMissing($meal);
    }

    public function test_service_rechecks_stale_names_types_and_locks(): void
    {
        $service = app(MealService::class);
        $meal = $this->meal();
        $foreign = $this->event('Other')->mealTypes()->firstOrFail();
        foreach ([['name' => 'FRI DINNER'], ['meal_type_id' => $foreign->id], ['ends_at' => '17:30']] as $changes) {
            try {
                $service->create($this->event, $this->data(['name' => 'New', ...$changes]));
                $this->fail('Expected service validation.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey(array_key_first($changes), $error->errors());
            }
        }
        Event::findOrFail($this->event->id)->lock();
        foreach ([
            fn () => $service->create($this->event, $this->data(['name' => 'New'])),
            fn () => $service->update($this->event, $meal, $this->data()),
            fn () => $service->destroy($this->event, $meal),
        ] as $write) {
            try {
                $write();
                $this->fail('Expected the freshly locked event to be refused.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }

    public function test_database_guards_names_windows_and_referenced_types(): void
    {
        $meal = $this->meal();
        foreach ([
            fn () => $this->event->meals()->create([
                'name' => 'FRI DINNER', 'meal_type_id' => $meal->meal_type_id,
                'date' => '2027-07-10', 'starts_at' => '17:30:00', 'ends_at' => '19:30:00',
            ]),
            fn () => $this->event->meals()->create([
                'name' => 'Empty window', 'meal_type_id' => $meal->meal_type_id,
                'date' => '2027-07-10', 'starts_at' => '12:00:00', 'ends_at' => '12:00:00',
            ]),
            fn () => $meal->mealType->delete(),
        ] as $invalidWrite) {
            try {
                DB::transaction($invalidWrite);
                $this->fail('Expected a database constraint.');
            } catch (QueryException) {
                $this->assertModelExists($meal);
            }
        }
        $this->assertSame(1, $this->event->meals()->count());
    }

    public function test_unicode_names_can_use_the_entire_display_limit(): void
    {
        $name = str_repeat('İ', 255);
        $this->post(route('meals.store', $this->event), $this->data(['name' => $name]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meals', ['name' => $name, 'name_key' => mb_strtolower($name)]);
    }

    private function event(string $name): Event
    {
        return app(EventService::class)->create([
            'name' => $name, 'starts_on' => '2027-07-10', 'ends_on' => '2027-07-12', 'timezone' => 'America/Vancouver',
        ]);
    }

    private function data(array $changes = [], ?Event $event = null): array
    {
        $event ??= $this->event;

        return [
            'name' => 'Fri Dinner', 'meal_type_id' => $event->mealTypes()->where('name', 'Dinner')->sole()->id,
            'date' => '2027-07-10', 'starts_at' => '17:30', 'ends_at' => '19:30', ...$changes,
        ];
    }

    private function meal(array $changes = [], ?Event $event = null): Meal
    {
        return app(MealService::class)->create($event ?? $this->event, $this->data($changes, $event));
    }

    private function table(array $query = []): TestResponse
    {
        return $this->getJson(route('meals.data', ['draw' => 1, 'start' => 0, 'length' => 25, ...$query]));
    }
}
