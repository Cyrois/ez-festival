<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Models\ShiftAssignment;
use App\Models\ShiftMeal;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Queries\MealReportQuery;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealOverrideService;
use App\Services\MealService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MealReportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private TeamEngagement $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = app(EventService::class)->create([
            'name' => 'Sunrise Folk Fest 2026', 'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-04', 'timezone' => 'America/Vancouver',
        ]);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->actingAs($this->user);
        $this->member = $this->createTeamEngagement(['event_id' => $this->event->id,
            'person_id' => Person::create(['name' => 'Ava Lee', 'email' => 'ava@example.test'])->id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $this->travelTo(Carbon::parse('2026-10-02 13:05', $this->event->timezone));
    }

    public function test_empty_event_has_no_days_or_rows(): void
    {
        $this->assertSame([], $this->reportDays());
        $this->assertSame([], $this->reportRows());
    }

    public function test_every_configured_meal_has_its_own_zero_row_in_window_order(): void
    {
        $this->meal('Sunday lunch', '2026-10-04');
        $this->meal('Friday dinner', '2026-10-02', 'Dinner');
        $this->meal('Friday lunch', '2026-10-02');
        $this->meal('Friday second lunch', '2026-10-02');
        $rows = $this->reportRows();
        $this->assertCount(4, $rows);
        $this->assertSame(['Friday lunch', 'Friday second lunch', 'Friday dinner', 'Sunday lunch'], array_column($rows, 'meal_name'));
        $this->assertSame(['Lunch', 'Lunch', 'Dinner', 'Lunch'], array_column($rows, 'meal_type'));
        $this->assertSame(['2026-10-02', '2026-10-02', '2026-10-02', '2026-10-04'], array_column($rows, 'date'));
        foreach ($rows as $row) {
            $this->assertSame([0, 0, 0, 0, 0], $this->counts($row));
            $this->assertSame(0, $row['assigned']);
        }
    }

    public function test_separate_shift_grants_count_without_merging_named_meals_of_the_same_date_and_type(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $this->grant($meal);
        $this->grant($meal);
        $this->grant($this->meal('Later lunch', '2026-10-02'));
        $rows = $this->reportRows();
        $this->assertCount(2, $rows);
        $this->assertSame($meal->id, $rows[0]['meal_id']);
        $this->assertSame([2, 0, 2, 0, 2], $this->counts($rows[0]));
        $this->assertSame([1, 0, 1, 0, 1], $this->counts($rows[1]));
        $this->assertSame(2, app(MealEntitlementQuery::class)->projected($this->event)->get($meal->id));
    }

    public function test_warning_confirmed_claims_count_as_used_without_becoming_overrides(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $first = $this->grant($meal);
        $second = $this->grant($meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $first->id);
        $result = app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $second->id, true);
        $this->assertSame('claimed', $result['status']);
        $this->assertNotNull($second->fresh()->warning_overridden_at);
        $this->assertSame([2, 2, 0, 0, 0], $this->counts($this->reportRows()[0]));
    }

    public function test_retired_unused_grants_drop_out_and_used_grants_survive_deleted_sources(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $used = $this->grant($meal);
        $unused = $this->grant($meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $used->id);
        $used->update(['is_active' => false]);
        $unused->update(['is_active' => false]);
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($this->reportRows()[0]));
        $this->assertSame(0, $this->reportRows()[0]['assigned']);

        foreach ([$used, $unused] as $grant) {
            ShiftMeal::findOrFail($grant->shift_meal_id)->delete();
            ShiftAssignment::findOrFail($grant->shift_assignment_id)->delete();
            $this->event->shifts()->findOrFail($grant->source_shift_id)->delete();
        }
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($this->reportRows()[0]));
        $this->assertSame(0, $this->reportRows()[0]['assigned']);
    }

    public function test_saved_claim_date_and_type_survive_later_named_meal_edits(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $grant = $this->grant($meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $grant->id);
        ShiftMeal::findOrFail($grant->shift_meal_id)->delete();
        app(MealService::class)->update($this->event, $meal, [
            'name' => 'Saturday dinner', 'meal_type_id' => $this->event->mealTypes()->where('name', 'Dinner')->sole()->id,
            'date' => '2026-10-03', 'starts_at' => '17:00', 'ends_at' => '19:00',
        ]);
        $rows = $this->reportRows();
        $this->assertCount(1, $rows);
        $this->assertSame($meal->id, $rows[0]['meal_id']);
        $this->assertSame('Saturday dinner', $rows[0]['meal_name']);
        $this->assertSame('2026-10-03', $rows[0]['date']);
        $this->assertSame('Dinner', $rows[0]['meal_type']);
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($rows[0]));
        $this->assertSame('2026-10-02', $grant->fresh()->meal_date->toDateString());
        $this->assertSame('Friday lunch', $grant->fresh()->meal_name);
        $this->assertNotSame($meal->fresh()->meal_type_id, $grant->fresh()->meal_type_id);
    }

    public function test_used_and_extras_stay_with_their_named_meal_when_date_and_type_match(): void
    {
        $first = $this->meal('First lunch', '2026-10-02');
        $second = $this->meal('Second lunch', '2026-10-02');
        foreach ([$first, $second] as $meal) {
            $grant = $this->grant($meal);
            app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $grant->id, true);
        }
        app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $first->id);
        $rows = $this->reportRows();
        $this->assertCount(2, $rows);
        $this->assertSame([$first->id, $second->id], array_column($rows, 'meal_id'));
        $this->assertSame([1, 2, 0, 1, -1], $this->counts($rows[0]));
        $this->assertSame([1, 1, 0, 0, 0], $this->counts($rows[1]));
    }

    public function test_overrides_are_a_used_subset_and_unclaim_and_reclaim_update_counts_without_changing_projected(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $normal = app(MealAssignmentService::class)->assign($this->event, $this->member, $meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $normal->id);
        $override = app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id);
        $this->assertSame([1, 2, 0, 1, -1], $this->counts($this->reportRows()[0]));
        $this->assertSame(2, $this->reportRows()[0]['assigned']);
        app(MealClaimService::class)->unclaim($this->event, $this->member, $this->user, $override['assignment_id'], $override['claim_token']);
        $this->assertSame([1, 1, 0, 0, 0], $this->counts($this->reportRows()[0]));
        $this->assertSame(2, $this->reportRows()[0]['assigned']);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $override['assignment_id'], true);
        $this->assertSame([1, 2, 0, 1, -1], $this->counts($this->reportRows()[0]));
        $this->assertSame(2, $this->reportRows()[0]['assigned']);
    }

    public function test_named_meal_remaining_is_clamped_but_total_preserves_negative_values(): void
    {
        $lunch = $this->grant($this->meal('Friday lunch', '2026-10-02'));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $lunch->id);
        $lunch->update(['is_active' => false]);
        $this->grant($this->meal('Friday dinner', '2026-10-02', 'Dinner'));
        $this->grant($this->meal('Saturday lunch', '2026-10-03'));
        $rows = $this->reportRows();
        $this->assertCount(3, $rows);
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($rows[0]));
        $this->assertSame([1, 0, 1, 0, 1], $this->counts($rows[1]));
        $this->assertSame([1, 0, 1, 0, 1], $this->counts($rows[2]));
    }

    public function test_unclaimed_extras_do_not_change_used_extras_remaining_or_total(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id, false);
        $this->assertSame([0, 0, 0, 0, 0], $this->counts($this->reportRows()[0]));
        $this->assertSame(1, $this->reportRows()[0]['assigned']);
    }

    public function test_overnight_claims_count_under_the_meal_start_date(): void
    {
        $meal = $this->meal('Friday midnight', '2026-10-02', 'Midnight', '23:00', '01:00');
        $grant = app(MealAssignmentService::class)->assign($this->event, $this->member, $meal);
        $this->travelTo(Carbon::parse('2026-10-03 00:30', $this->event->timezone));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $grant->id);
        $rows = $this->reportRows();
        $this->assertSame('2026-10-02', $rows[0]['date']);
        $this->assertSame([1, 1, 0, 0, 0], $this->counts($rows[0]));
    }

    public function test_current_event_scopes_all_configured_assigned_projected_and_used_rows(): void
    {
        $this->grant($this->meal('Friday lunch', '2026-10-02'));
        $foreign = app(EventService::class)->create([
            'name' => 'Other festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'UTC',
        ]);
        $foreignMember = $this->createTeamEngagement(['event_id' => $foreign->id,
            'person_id' => $this->member->person_id, 'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $meal = $foreign->meals()->create([
            'name' => 'Private dinner', 'date' => '2026-10-02', 'meal_type_id' => $foreign->mealTypes()->where('name', 'Dinner')->sole()->id,
            'starts_at' => '17:00', 'ends_at' => '19:00',
        ]);
        app(MealOverrideService::class)->give($foreign, $foreignMember, $this->user, $meal->id);
        $rows = $this->reportRows();
        $this->assertCount(1, $rows);
        $this->assertSame([1, 0, 1, 0, 1], $this->counts($rows[0]));
        $this->assertSame(1, $rows[0]['assigned']);
        $this->assertSame(1, $this->reportDays()[0]['totals']['assigned']);
    }

    public function test_kitchen_permissions_without_report_access_refuse_page_before_loading_data(): void
    {
        $this->mock(MealReportQuery::class)->shouldNotReceive('rows');
        foreach (['meals.view', 'meals.edit', 'meals.claim', 'meals.override', 'meals.undo_claim'] as $permission) {
            $this->grantRoleAccess($this->user, [$permission]);
            $this->get(route('reports.index'))->assertForbidden();
            $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
                ->where('permissions', fn ($permissions) => $permissions['meals.view'] && ! $permissions['meals.report.view'])
                ->missing('meals'));
        }
    }

    public function test_view_meal_report_alone_is_sufficient_and_revocation_refuses_page(): void
    {
        $this->meal('Friday lunch', '2026-10-02');
        $this->grantRoleAccess($this->user, ['meals.report.view']);
        $this->assertCount(1, $this->reportRows());
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($permissions) => $permissions['meals.report.view'] && ! $permissions['meals.view'])
            ->missing('meals'));
        foreach (['meals.index', 'meals.settings', 'meals.data', 'meals.people.index', 'meals.types.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role->update(['permissions' => ['team.view']]);
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($permissions) => ! $permissions['meals.report.view'] && ! $permissions['meals.view'])
            ->missing('meals'));
    }

    public function test_locked_event_is_readable_by_view_only_roles_and_admins(): void
    {
        $this->meal('Friday lunch', '2026-10-02');
        $this->event->lock();
        $this->grantRoleAccess($this->user, ['meals.report.view']);
        $this->assertCount(1, $this->reportRows());
        $this->grantAdminAccess($this->user);
        $this->assertCount(1, $this->reportRows());
    }

    public function test_report_permission_does_not_carry_between_events_or_survive_role_deactivation(): void
    {
        $this->grantRoleAccess($this->user, ['meals.report.view']);
        $reportRole = $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role;
        $otherEvent = Event::create([
            'name' => 'Other festival', 'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-02', 'timezone' => 'America/Vancouver',
        ]);
        $this->createTeamEngagement(['event_id' => $otherEvent->id,
            'person_id' => $this->user->person_id,
            'role_id' => Role::create(['name' => 'Other event viewer', 'permissions' => ['team.view']])->id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);

        $this->get(route('reports.index'))->assertOk();
        $this->user->setCurrentEvent($otherEvent);
        $this->get(route('reports.index'))->assertForbidden();

        $this->user->setCurrentEvent($this->event);
        $reportRole->update(['active' => false]);
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($permissions) => ! $permissions['meals.report.view']));
    }

    public function test_assigned_counts_each_source_and_removed_overrides_disappear(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $shift = $this->grant($meal);
        $direct = app(MealAssignmentService::class)->assign($this->event, $this->member, $meal);
        foreach ([$shift, $direct] as $assignment) {
            app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $assignment->id, true);
        }
        app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id);
        $unused = app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id, false);

        $row = $this->reportRows()[0];
        $this->assertSame(4, $row['assigned']);
        $this->assertSame([2, 3, 0, 1, -1], $this->counts($row));
        $this->assertSame(2, app(MealEntitlementQuery::class)->forEvent($this->event)->count());

        app(MealOverrideService::class)->remove($this->event, $this->member, $this->user, $unused['assignment_id']);
        $this->assertSame(3, $this->reportRows()[0]['assigned']);
        $this->assertSame([2, 3, 0, 1, -1], $this->counts($this->reportRows()[0]));
    }

    public function test_removed_shift_recipients_do_not_count_as_assigned_but_keep_claims(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $used = $this->grant($meal);
        $unused = $this->grant($meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $used->id);
        foreach ([$used, $unused] as $assignment) {
            app(MealAssignmentService::class)->syncShiftMeal(ShiftMeal::findOrFail($assignment->shift_meal_id), []);
        }

        $row = $this->reportRows()[0];
        $this->assertSame(0, $row['assigned']);
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($row));
        $days = $this->reportDays();
        $this->assertSame($days[0]['totals'], $days[0]['meal_types'][0]['totals']);
        $this->assertSame(0, $days[0]['totals']['assigned']);
        $this->assertSame(-1, $days[0]['totals']['total']);
    }

    public function test_tallies_and_day_totals_recalculate_remaining_instead_of_summing_clamped_rows(): void
    {
        $first = $this->meal('Lunch A', '2026-10-02');
        $second = $this->meal('Lunch B', '2026-10-02');
        for ($index = 0; $index < 3; $index++) {
            $assignment = app(MealAssignmentService::class)->assign($this->event, $this->member, $first);
            app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $assignment->id, true);
            if ($index > 0) {
                $assignment->update(['is_active' => false]);
            }
        }
        for ($index = 0; $index < 5; $index++) {
            app(MealAssignmentService::class)->assign($this->event, $this->member, $second);
        }
        $dinner = $this->meal('Dinner', '2026-10-02', 'Dinner');
        for ($index = 0; $index < 4; $index++) {
            app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $dinner->id);
        }
        $this->meal('Sunday lunch', '2026-10-04');

        $days = $this->reportDays();
        $this->assertSame(['2026-10-02', '2026-10-04'], array_column($days, 'date'));
        $this->assertSame(['Lunch', 'Dinner'], array_column($days[0]['meal_types'], 'meal_type'));
        $group = $days[0]['meal_types'][0];
        $this->assertSame([$first->id, $second->id], array_column($group['meals'], 'meal_id'));
        $this->assertSame([0, 5], array_column($group['meals'], 'remaining'));
        $totals = ['assigned' => 6, 'projected' => 6, 'used' => 3, 'extras' => 0, 'remaining' => 3, 'total' => 3];
        $this->assertSame($totals, $group['totals']);
        $this->assertSame(['assigned' => 10, 'projected' => 6, 'used' => 7, 'extras' => 4, 'remaining' => 0, 'total' => -1], $days[0]['totals']);
        $this->assertSame(0, $days[1]['totals']['assigned']);
    }

    public function test_claimed_overrides_are_subtracted_once_in_named_meal_type_and_day_totals(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        for ($index = 0; $index < 3; $index++) {
            app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id);
        }
        $day = $this->reportDays()[0];
        $group = $day['meal_types'][0];
        foreach ([$group['meals'][0], $group['totals'], $day['totals']] as $row) {
            $this->assertSame(3, $row['assigned']);
            $this->assertSame([0, 3, 0, 3, -3], $this->counts($row));
        }
    }

    public function test_overnight_shift_assignments_group_by_the_meals_own_date(): void
    {
        $meal = $this->meal('Saturday breakfast', '2026-10-03', 'Breakfast', '07:00', '10:00');
        $assignment = $this->grant($meal);
        $shift = $this->event->shifts()->findOrFail($assignment->source_shift_id);
        $shift->update(['starts_at' => Carbon::parse('2026-10-02 22:00', $this->event->timezone)->utc()]);

        $day = $this->reportDays()[0];
        $this->assertSame('2026-10-03', $day['date']);
        $this->assertSame('Breakfast', $day['meal_types'][0]['meal_type']);
        $this->assertSame(1, $day['totals']['assigned']);
    }

    public function test_meal_types_with_equal_windows_stay_in_separate_groups_and_zero_meals_stay_visible(): void
    {
        $lunch = $this->event->mealTypes()->where('name', 'Lunch')->sole();
        $dinner = $this->event->mealTypes()->where('name', 'Dinner')->sole();
        $dinner->update(['starts_at' => $lunch->starts_at, 'ends_at' => $lunch->ends_at]);
        $this->meal('Z lunch', '2026-10-02');
        $this->meal('A dinner', '2026-10-02', 'Dinner');
        $this->meal('A lunch', '2026-10-02');
        $this->meal('Midnight', '2026-10-02', 'Midnight', '23:00', '01:00');

        $day = $this->reportDays()[0];
        $this->assertSame([$lunch->id, $dinner->id, $this->event->mealTypes()->where('name', 'Midnight')->sole()->id], array_column($day['meal_types'], 'meal_type_id'));
        $this->assertSame(['A lunch', 'Z lunch'], array_column($day['meal_types'][0]['meals'], 'meal_name'));
        foreach ($day['meal_types'] as $group) {
            $this->assertSame([0, 0, 0, 0, 0], $this->counts($group['totals']));
            $this->assertSame(0, $group['totals']['assigned']);
        }
    }

    public function test_export_url_is_not_found_and_its_route_no_longer_exists(): void
    {
        $this->get('/reports/meals/export')->assertNotFound();
        $this->assertFalse(Route::has('reports.meals.export'));
    }

    private function reportDays(): array
    {
        $response = $this->get(route('reports.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->has('meals.days'));

        return $response->inertiaProps('meals')['days'];
    }

    private function reportRows(): array
    {
        return collect($this->reportDays())->flatMap(fn (array $day) => collect($day['meal_types'])->flatMap(fn (array $group) => $group['meals']))->all();
    }

    private function counts(array $row): array
    {
        return [$row['projected'], $row['used'], $row['remaining'], $row['extras'], $row['total']];
    }

    private function meal(string $name, string $date, string $type = 'Lunch', string $start = '12:00', string $end = '14:00'): Meal
    {
        return app(MealService::class)->create($this->event, [
            'name' => $name, 'meal_type_id' => $this->event->mealTypes()->where('name', $type)->sole()->id,
            'date' => $date, 'starts_at' => $start, 'ends_at' => $end,
        ]);
    }

    private function grant(Meal $meal): MealAssignment
    {
        $start = Carbon::parse($meal->date->toDateString().' 09:00', $this->event->timezone)->utc();
        $shift = $this->event->shifts()->create([
            'name' => 'Gate shift', 'location_id' => $this->event->locations()->firstOrCreate(['name' => 'Gate'])->id,
            'starts_at' => $start, 'ends_at' => $start->copy()->addHours(12),
        ]);
        $recipient = $shift->assignments()->create([
            'team_engagement_id' => $this->member->id, 'role_id' => Role::firstOrCreate(['name' => 'Crew'])->id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at,
        ]);
        $row = $shift->meals()->create(['meal_id' => $meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$recipient->id]);

        return MealAssignment::where('shift_meal_id', $row->id)->sole();
    }
}
