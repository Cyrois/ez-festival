<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealService;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamShiftMealsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private Shift $shift;

    private Role $role;

    private Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = app(EventService::class)->create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->actingAs($this->user);
        $this->role = Role::create(['name' => 'Crew']);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->shift = app(ShiftService::class)->create($this->event, ['name' => 'Gate shift', 'location_id' => $location->id, 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T22:00']);
        $this->meal = $this->meal();
    }

    public function test_create_resolves_draft_people_and_preserves_only_the_selected_recipients(): void
    {
        $first = $this->member();
        $second = $this->member();
        $data = $this->data([
            'assignment_additions' => [$this->addition($first, -1), $this->addition($second, -2)],
            'meals' => [['meal_id' => $this->meal->id, 'assignment_keys' => [-1]]],
        ]);
        $this->post(route('team.shifts.store', $this->event), $data)->assertSessionHasNoErrors();
        $created = $this->event->shifts()->whereKeyNot($this->shift->id)->sole();
        $row = $created->meals()->sole();
        $this->assertSame([$first->id], $row->assignments()->pluck('shift_assignments.team_engagement_id')->all());
        $this->assertSame(1, app(MealEntitlementQuery::class)->forEvent($this->event)->count());
        $this->assertSame(0, app(MealEntitlementQuery::class)->forEvent($this->event)->where('team_engagements.id', $second->id)->count());
        $this->assertFalse(Schema::hasColumn('shift_meals', 'applies_to_everyone'));
    }

    public function test_update_changes_recipients_preserves_row_identity_and_later_people_get_nothing(): void
    {
        $first = $this->assign();
        $row = $this->shift->meals()->create(['meal_id' => $this->meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$first->id]);
        $later = $this->member();
        $this->save(['assignment_additions' => [$this->addition($later, -1)]])->assertSessionHasNoErrors();
        $this->assertSame([$first->id], $row->assignments()->pluck('shift_assignments.id')->all());
        $second = $this->shift->assignments()->where('team_engagement_id', $later->id)->sole();
        $this->save(['meals' => [['meal_id' => $this->meal->id, 'assignment_keys' => [$second->id]]]])->assertSessionHasNoErrors();
        $this->assertSame($row->id, $this->shift->meals()->sole()->id);
        $this->assertSame([$second->id], $row->assignments()->pluck('shift_assignments.id')->all());
        $this->save(['meals' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shift_meals', 0);
        $this->assertSame(0, MealAssignment::query()->where('is_active', true)->whereNotNull('shift_assignment_id')->count());
    }

    public function test_same_person_can_receive_multiple_meals_and_the_same_meal_from_two_shifts_counts_twice(): void
    {
        $assignment = $this->assign();
        $lunch = $this->meal(['name' => 'Lunch', 'starts_at' => '12:30', 'ends_at' => '14:00']);
        $this->save(['meals' => [
            ['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id]],
            ['meal_id' => $lunch->id, 'assignment_keys' => [$assignment->id]],
        ]])->assertSessionHasNoErrors();
        $other = app(ShiftService::class)->create($this->event, $this->data(['name' => 'Second shift']));
        $second = app(ShiftAssignmentService::class)->create($other, ['team_engagement_id' => $assignment->team_engagement_id, 'role_id' => $this->role->id, 'hours_mode' => 'full_shift']);
        $row = $other->meals()->create(['meal_id' => $this->meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$second->id]);
        $query = app(MealEntitlementQuery::class);
        $this->assertSame(3, $query->forEvent($this->event)->where('team_engagements.id', $assignment->team_engagement_id)->count());
        $this->assertEquals(2, $query->projected($this->event)[$this->meal->id]);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->has('shift.meals', 2)->where('shift.meals.0.assignment_ids', [$assignment->id])
            ->where('shift.meals.0.meal.name', $this->meal->name)->where('canConfigureMeals', true));
        $this->getJson(route('team.scheduling.shifts'))->assertJsonPath('data.0.meals.0.meal.name', $this->meal->name);
        $this->getJson(route('team.scheduling.grid', ['date' => '2026-10-01']))->assertOk()
            ->assertJsonPath('data.0.shifts.0.meals.0.name', $this->meal->name)
            ->assertJsonMissingPath('data.0.shifts.0.meals.0.assignment_ids');
        $this->getJson(route('team.scheduling.roster', ['date' => '2026-10-01', 'location_id' => $this->shift->location_id]))
            ->assertOk()->assertJsonPath('data.0.meals.0.assignment_ids', [$assignment->id]);
    }

    public function test_requests_refuse_foreign_unknown_wrong_day_duplicate_empty_and_unlisted_recipients_atomically(): void
    {
        $assignment = $this->assign();
        $other = app(EventService::class)->create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $foreign = $this->meal([], $other);
        $tomorrow = $this->meal(['name' => 'Tomorrow', 'date' => '2026-10-02']);
        $row = ['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id]];
        foreach ([
            [['meal_id' => $foreign->id, 'assignment_keys' => [$assignment->id]]],
            [['meal_id' => 999999999, 'assignment_keys' => [$assignment->id]]],
            [['meal_id' => $tomorrow->id, 'assignment_keys' => [$assignment->id]]],
            [$row, $row],
            [['meal_id' => $this->meal->id, 'assignment_keys' => []]],
            [['meal_id' => $this->meal->id, 'assignment_keys' => [-999]]],
            [['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id, $assignment->id]]],
            [['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id], 'everyone' => true]],
        ] as $rows) {
            $this->save(['name' => 'Must roll back', 'meals' => $rows])->assertSessionHasErrors();
            $this->assertSame('Gate shift', $this->shift->fresh()->name);
            $this->assertDatabaseCount('shift_meals', 0);
        }
        $unlisted = $this->member();
        $this->post(route('team.shifts.store', $this->event), $this->data([
            'assignment_additions' => [$this->addition($unlisted, -1)], 'meals' => [$row],
        ]))->assertSessionHasErrors('meals.0.assignment_keys');
        $this->assertSame(1, $this->event->shifts()->count());
        $this->save(['assignment_removals' => [$assignment->id], 'meals' => [$row]])->assertSessionHasErrors('meals.0.assignment_keys');
        $this->assertModelExists($assignment);
    }

    public function test_removing_the_last_recipient_requires_removing_the_meal_row_in_the_same_save(): void
    {
        $assignment = $this->assign();
        $this->save(['meals' => [['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id]]]])->assertSessionHasNoErrors();
        $this->save(['assignment_removals' => [$assignment->id]])->assertSessionHasErrors('meals.0.assignment_keys');
        $this->assertModelExists($assignment);
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $assignment]))->assertSessionHasErrors('meals');
        try {
            app(ShiftAssignmentService::class)->delete($this->shift, $assignment);
            $this->fail('Expected last-recipient refusal.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('meals', $error->errors());
        }
        $this->save(['assignment_removals' => [$assignment->id], 'meals' => []])->assertSessionHasNoErrors();
        $this->assertModelMissing($assignment);
        $this->assertSame(0, MealAssignment::query()->where('is_active', true)->whereNotNull('shift_assignment_id')->count());
    }

    public function test_overnight_shift_accepts_both_days_but_midnight_end_excludes_the_next_day(): void
    {
        $member = $this->member();
        $tomorrow = $this->meal(['name' => 'Tomorrow', 'date' => '2026-10-02', 'starts_at' => '00:30', 'ends_at' => '02:00']);
        $data = $this->data(['name' => 'Overnight', 'starts_at' => '2026-10-01T23:00', 'ends_at' => '2026-10-02T03:00',
            'assignment_additions' => [$this->addition($member, -1)], 'meals' => [
                ['meal_id' => $this->meal->id, 'assignment_keys' => [-1]],
                ['meal_id' => $tomorrow->id, 'assignment_keys' => [-1]],
            ],
        ]);
        $this->post(route('team.shifts.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->event->shifts()->where('name', 'Overnight')->sole()->meals()->count());
        $this->post(route('team.shifts.store', $this->event), [...$data, 'ends_at' => '2026-10-02T00:00'])->assertSessionHasErrors('meals.1.meal_id');
        $this->assertSame(2, $this->event->shifts()->count());
    }

    public function test_shift_dates_must_keep_existing_meals_on_the_shifts_days_even_when_meals_are_omitted(): void
    {
        $assignment = $this->assign();
        $this->save(['meals' => [['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id]]]])->assertSessionHasNoErrors();
        $this->save(['starts_at' => '2026-10-02T12:00', 'ends_at' => '2026-10-02T22:00',
            'assignment_removals' => [$assignment->id]])->assertSessionHasErrors('meals.0.meal_id');
        $this->assertSame('2026-10-01', $this->shift->fresh()->starts_at->format('Y-m-d'));
        $this->save(['starts_at' => '2026-10-02T12:00', 'ends_at' => '2026-10-02T22:00',
            'assignment_removals' => [$assignment->id], 'meals' => []])->assertSessionHasNoErrors();
    }

    public function test_assigned_meals_cannot_be_edited_or_deleted_until_every_shift_reference_is_removed(): void
    {
        $assignment = $this->assign();
        $row = $this->shift->meals()->create(['meal_id' => $this->meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$assignment->id]);
        $data = ['name' => 'Changed', 'meal_type_id' => $this->meal->meal_type_id, 'date' => '2026-10-02', 'starts_at' => '18:00', 'ends_at' => '20:00'];
        $this->put(route('meals.update', [$this->event, $this->meal]), $data)->assertSessionHasErrors('meal');
        $this->get(route('meals.edit', $this->meal))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', false)->where('meal.assigned_to_shifts', true));
        $this->getJson(route('meals.data', ['draw' => 1, 'start' => 0, 'length' => 25]))->assertOk()->assertJsonPath('data.0.assigned_to_shifts', true);
        $this->delete(route('meals.destroy', [$this->event, $this->meal]))->assertSessionHasErrors('meal');
        $error = app(MealService::class)->deletionErrors($this->meal)['meal'];
        $this->assertStringContainsString('Gate', $error);
        $this->assertStringContainsString('2026-10-01', $error);
        foreach (['update', 'destroy'] as $method) {
            try {
                app(MealService::class)->$method($this->event, $this->meal, ...($method === 'update' ? [$data] : []));
                $this->fail('Expected assigned-meal refusal.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('meal', $error->errors());
            }
        }
        $this->save(['meals' => []])->assertSessionHasNoErrors();
        $this->put(route('meals.update', [$this->event, $this->meal]), $data)->assertSessionHasNoErrors();
        $this->assertSame('Changed', $this->meal->fresh()->name);
    }

    public function test_used_claim_survives_recipient_and_meal_row_removal(): void
    {
        $first = $this->assign();
        $second = $this->assign();
        $row = $this->shift->meals()->create(['meal_id' => $this->meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$first->id, $second->id]);
        $this->travelTo(Carbon::parse('2026-10-01 12:00', $this->event->timezone));
        app(MealClaimService::class)->claim($this->event, $first->teamEngagement, $this->user, MealAssignment::query()->where('team_engagement_id', $first->team_engagement_id)->where('source_shift_id', $this->shift->id)->where('meal_id', $this->meal->id)->sole()->id);
        $this->save(['assignment_removals' => [$first->id], 'meals' => [['meal_id' => $this->meal->id, 'assignment_keys' => [$second->id]]]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meal_assignments', ['team_engagement_id' => $first->team_engagement_id, 'shift_meal_id' => $row->id]);
        $this->save(['meals' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meal_assignments', ['meal_id' => $this->meal->id, 'shift_meal_id' => null]);
        $this->assertSame(0, app(MealEntitlementQuery::class)->forEvent($this->event)->count());
    }

    public function test_copy_permissions_locked_events_and_stale_service_validation(): void
    {
        $assignment = $this->assign();
        $row = ['meal_id' => $this->meal->id, 'assignment_keys' => [$assignment->id]];
        $this->save(['meals' => [$row]])->assertSessionHasNoErrors();
        $this->get(route('team.shifts.copy', $this->shift))->assertInertia(fn (Assert $page) => $page->missing('prefill.meals'));
        $this->post(route('team.shifts.store', $this->event), $this->data(['name' => 'Copy']))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->event->shifts()->where('name', 'Copy')->sole()->meals()->count());
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->has('shift.meals', 1)->where('canManage', false)->where('canConfigureMeals', false));
        $this->save(['meals' => []])->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->event->lock();
        $this->save(['meals' => []])->assertForbidden();
        $this->event->unlock();
        $tomorrow = $this->meal(['name' => 'Tomorrow', 'date' => '2026-10-02']);
        try {
            app(ShiftService::class)->update($this->shift, $this->data(['name' => 'Must roll back', 'meals' => [['meal_id' => $tomorrow->id, 'assignment_keys' => [$assignment->id]]]]));
            $this->fail('Expected service validation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('meals.0.meal_id', $error->errors());
        }
        $this->assertSame('Gate shift', $this->shift->fresh()->name);
    }

    private function data(array $changes = []): array
    {
        return ['name' => 'Gate shift', 'location_id' => $this->shift->location_id, 'starts_at' => '2026-10-01T12:00', 'ends_at' => '2026-10-01T22:00', ...$changes];
    }

    private function save(array $changes)
    {
        return $this->put(route('team.shifts.update', [$this->event, $this->shift]), $this->data($changes));
    }

    private function meal(array $changes = [], ?Event $event = null): Meal
    {
        $event ??= $this->event;

        return app(MealService::class)->create($event, ['name' => 'Fri Dinner', 'meal_type_id' => $event->mealTypes()->where('name', 'Dinner')->sole()->id, 'date' => '2026-10-01', 'starts_at' => '17:30', 'ends_at' => '19:30', ...$changes]);
    }

    private function member(): TeamEngagement
    {
        $person = Person::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail()]);

        return TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $person->id, 'role_id' => $this->role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function addition(TeamEngagement $member, int $key): array
    {
        return ['role_id' => $this->role->id, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift', 'client_key' => $key];
    }

    private function assign()
    {
        return app(ShiftAssignmentService::class)->create($this->shift, $this->addition($this->member(), -1));
    }
}
