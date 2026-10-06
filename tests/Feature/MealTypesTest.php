<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\EventService;
use App\Services\MealTypeService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MealTypesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = app(EventService::class)->create([
            'name' => 'Meals festival', 'starts_on' => '2027-07-10', 'ends_on' => '2027-07-12', 'timezone' => 'America/Vancouver',
        ]);
        $this->user = User::factory()->create();
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->grantRoleAccess($this->user, ['meals.edit']);
        $this->actingAs($this->user);
    }

    public function test_pages_and_table_expose_only_the_current_events_types(): void
    {
        $this->get(route('meals.index'))->assertInertia(fn (Assert $page) => $page->component('Meals/Index'));
        $this->get(route('meals.settings'))->assertInertia(fn (Assert $page) => $page
            ->component('Meals/Settings')->where('event.id', $this->event->id)->where('canEdit', true)
            ->where('permissions', fn ($permissions) => $permissions['meals.view'] && $permissions['meals.edit']));
        $other = app(EventService::class)->create([
            'name' => 'Other', 'starts_on' => '2027-07-10', 'ends_on' => '2027-07-12', 'timezone' => 'UTC',
        ]);
        $other->mealTypes()->create(['name' => 'Foreign snack', 'starts_at' => '02:00:00', 'ends_at' => '03:00:00']);

        $this->table()->assertOk()->assertJsonPath('recordsTotal', 4)->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Breakfast')->assertJsonPath('data.0.starts_at', '07:00')
            ->assertJsonPath('data.3.name', 'Midnight')->assertJsonPath('data.3.ends_at', '01:00');
        $this->table(['search' => ['value' => 'lUnCh']])->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Lunch');
        $this->table(['start' => 1, 'length' => 1])->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lunch');
        $this->table(['order' => [['column' => 0, 'dir' => 'desc']]])->assertJsonPath('data.0.name', 'Midnight');
        $this->table(['search' => ['value' => 'Foreign']])->assertJsonPath('recordsFiltered', 0);
        $this->table(['length' => -1])->assertUnprocessable()->assertJsonValidationErrors('length');
    }

    public function test_add_and_update_preserve_the_id_and_allow_touching_boundaries(): void
    {
        $this->post(route('meals.types.store', $this->event), [
            'name' => ' Brunch ', 'starts_at' => '10:00', 'ends_at' => '11:00',
        ])->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $type = $this->event->mealTypes()->where('name', 'Brunch')->sole();
        $this->assertSame('brunch', $type->name_key);
        $this->put(route('meals.types.update', [$this->event, $type]), [
            'name' => 'Crew snack', 'starts_at' => '14:00', 'ends_at' => '17:00',
        ])->assertRedirect(route('meals.settings'))->assertSessionHasNoErrors();
        $this->assertSame('Crew snack', $type->fresh()->name);
        $this->assertSame('14:00:00', $type->fresh()->starts_at);
        $this->assertSame(5, $this->event->mealTypes()->count());
        $this->table(['search' => ['value' => 'Crew']])->assertJsonPath('data.0.id', $type->id);
    }

    public function test_overnight_window_can_be_edited_without_conflicting_with_itself(): void
    {
        $type = $this->event->mealTypes()->where('name', 'Midnight')->sole();
        $this->put(route('meals.types.update', [$this->event, $type]), [
            'name' => 'Night meal', 'starts_at' => '23:30', 'ends_at' => '01:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame('01:00:00', $type->fresh()->ends_at);
        $this->post(route('meals.types.store', $this->event), [
            'name' => 'Early meal', 'starts_at' => '01:00', 'ends_at' => '07:00',
        ])->assertSessionHasNoErrors();
    }

    #[DataProvider('overlaps')]
    public function test_overlapping_windows_fail_with_the_other_types_name(string $start, string $end, string $other): void
    {
        $this->post(route('meals.types.store', $this->event), [
            'name' => 'Conflict', 'starts_at' => $start, 'ends_at' => $end,
        ])->assertSessionHasErrors('ends_at', fn ($message) => str_contains($message, $other));
        $this->assertDatabaseMissing('meal_types', ['event_id' => $this->event->id, 'name' => 'Conflict']);
    }

    public static function overlaps(): array
    {
        return [
            'inside' => ['11:30', '12:30', 'Lunch (11:00–14:00)'],
            'enclosing' => ['10:30', '14:30', 'Lunch'],
            'same' => ['11:00', '14:00', 'Lunch'],
            'overnight tail' => ['00:30', '07:00', 'Midnight (23:00–01:00)'],
            'overnight head' => ['22:00', '23:30', 'Midnight'],
            'wrapping both sides' => ['22:30', '01:30', 'Midnight'],
            'ends at midnight' => ['22:30', '00:00', 'Midnight'],
        ];
    }

    #[DataProvider('invalidTypes')]
    public function test_form_requests_refuse_invalid_types(array $data, string $field): void
    {
        $this->postJson(route('meals.types.store', $this->event), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $type = $this->event->mealTypes()->where('name', 'Breakfast')->sole();
        $this->putJson(route('meals.types.update', [$this->event, $type]), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Breakfast', $type->fresh()->name);
        $this->assertSame(4, $this->event->mealTypes()->count());
    }

    public static function invalidTypes(): array
    {
        $valid = ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00'];

        return [
            'empty name' => [[...$valid, 'name' => '  '], 'name'],
            'long name' => [[...$valid, 'name' => str_repeat('a', 51)], 'name'],
            'case duplicate' => [[...$valid, 'name' => ' lUnCh '], 'name'],
            'missing start' => [[...$valid, 'starts_at' => ''], 'starts_at'],
            'missing end' => [[...$valid, 'ends_at' => null], 'ends_at'],
            'invalid time' => [[...$valid, 'starts_at' => '24:00'], 'starts_at'],
            'seconds' => [[...$valid, 'ends_at' => '03:00:01'], 'ends_at'],
            'equal' => [[...$valid, 'ends_at' => '02:00'], 'ends_at'],
        ];
    }

    public function test_foreign_records_and_noncurrent_event_writes_are_refused(): void
    {
        $other = app(EventService::class)->create([
            'name' => 'Foreign', 'starts_on' => '2027-07-10', 'ends_on' => '2027-07-12', 'timezone' => 'UTC',
        ]);
        $this->grantRoleAccess($this->user, ['meals.edit']);
        $type = $other->mealTypes()->firstOrFail();
        $data = ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00'];
        $this->put(route('meals.types.update', [$this->event, $type]), $data)->assertNotFound();
        $this->post(route('meals.types.store', $other), $data)->assertNotFound();
        $this->put(route('meals.types.update', [$other, $type]), $data)->assertNotFound();
        $this->assertSame('Breakfast', $type->fresh()->name);
    }

    public function test_view_only_access_and_role_revocation_take_effect_on_each_request(): void
    {
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->get(route('meals.settings'))->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)->where('permissions', fn ($permissions) => $permissions['meals.view']));
        $this->table()->assertOk();
        $data = ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00'];
        $this->post(route('meals.types.store', $this->event), $data)->assertForbidden();
        $this->put(route('meals.types.update', [$this->event, $this->event->mealTypes()->first()]), $data)->assertForbidden();
        $role = $this->user->person->teamEngagements()->where('event_id', $this->event->id)->firstOrFail()->role;
        $role->update(['permissions' => ['artists.view']]);
        $this->get(route('meals.index'))->assertForbidden();
        $this->get(route('meals.settings'))->assertForbidden();
        $this->table()->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('permissions', fn ($permissions) => ! $permissions['meals.view']));
        $role->update(['permissions' => ['meals.edit'], 'active' => false]);
        $this->get(route('meals.settings'))->assertRedirect(route('login'));
    }

    public function test_admin_can_edit_but_a_locked_event_is_read_only_for_everyone(): void
    {
        $this->grantAdminAccess($this->user);
        $data = ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00'];
        $this->post(route('meals.types.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->event->lock();
        $this->get(route('meals.settings'))->assertInertia(fn (Assert $page) => $page->where('event.is_locked', true));
        $this->table()->assertOk();
        foreach ([true, false] as $admin) {
            $this->user->forceFill(['is_admin' => $admin])->save();
            $this->post(route('meals.types.store', $this->event), $data)->assertForbidden();
            $this->put(route('meals.types.update', [$this->event, $this->event->mealTypes()->first()]), $data)->assertForbidden();
        }
    }

    public function test_service_rechecks_conflicts_and_locks_for_stale_inputs(): void
    {
        $service = app(MealTypeService::class);
        $data = ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00'];
        $service->create($this->event, $data);
        try {
            $service->create($this->event, [...$data, 'name' => 'Second snack']);
            $this->fail('Expected an overlap validation error.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('ends_at', $error->errors());
        }
        $fresh = Event::findOrFail($this->event->id);
        $fresh->lock();
        try {
            $service->create($this->event, [...$data, 'starts_at' => '04:00', 'ends_at' => '05:00']);
            $this->fail('Expected the freshly locked event to be refused.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_meals_permissions_round_trip_and_edit_includes_view(): void
    {
        $this->grantAdminAccess($this->user);
        $this->post(route('settings.roles.store'), ['name' => 'Kitchen setup', 'permissions' => ['meals.edit']])
            ->assertSessionHasNoErrors();
        $role = Role::where('name', 'Kitchen setup')->sole();
        $this->assertEqualsCanonicalizing(['meals.edit', 'meals.view'], $role->permissions);
        foreach (['settings.roles.create', 'settings.roles.edit'] as $routeName) {
            $this->get(route($routeName, $routeName === 'settings.roles.edit' ? $role : []))
                ->assertInertia(fn (Assert $page) => $page->has('permissionGroups.meals', 2)
                    ->where('permissionGroups.meals.0.label', 'View meals')
                    ->where('permissionGroups.meals.1.includes', ['meals.view']));
        }
    }

    public function test_database_uniqueness_is_event_scoped_and_case_insensitive(): void
    {
        try {
            DB::transaction(fn () => $this->event->mealTypes()->create([
                'name' => 'BREAKFAST', 'starts_at' => '02:00:00', 'ends_at' => '03:00:00',
            ]));
            $this->fail('Expected the unique constraint to refuse the duplicate.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(4, $this->event->mealTypes()->count());
        }
        $other = Event::create(['name' => 'Other', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
        $type = $other->mealTypes()->create(['name' => 'Breakfast', 'starts_at' => '02:00:00', 'ends_at' => '03:00:00']);
        $this->assertModelExists($type);
    }

    public function test_a_fifty_character_unicode_name_can_be_saved(): void
    {
        $name = str_repeat('İ', 50);
        $this->post(route('meals.types.store', $this->event), [
            'name' => $name, 'starts_at' => '02:00', 'ends_at' => '03:00',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meal_types', [
            'event_id' => $this->event->id, 'name' => $name, 'name_key' => mb_strtolower($name),
        ]);
    }

    public function test_database_refuses_an_empty_window(): void
    {
        try {
            DB::transaction(fn () => $this->event->mealTypes()->create([
                'name' => 'Empty window', 'starts_at' => '02:00:00', 'ends_at' => '02:00:00',
            ]));
            $this->fail('Expected the window check constraint to refuse equal endpoints.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('meal_types', ['name' => 'Empty window']);
        }
    }

    private function table(array $query = []): TestResponse
    {
        return $this->getJson(route('meals.types.index', array_replace_recursive([
            'draw' => 1, 'start' => 0, 'length' => 25,
        ], $query)));
    }
}
