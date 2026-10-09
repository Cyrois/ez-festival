<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftMeal;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamMemberMealsTest extends TestCase
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
            'name' => 'Team meals festival',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-04',
            'timezone' => 'America/Vancouver',
        ]);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->actingAs($this->user);
        $this->member = $this->event->teamEngagements()->create([
            'person_id' => Person::create(['name' => 'Ava Lee', 'email' => 'ava@example.test'])->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
        $this->travelTo(Carbon::parse('2026-10-02 13:05', $this->event->timezone));
    }

    public function test_all_days_include_separate_shift_grants_with_full_type_counts_and_event_local_used_time(): void
    {
        $lunch = $this->meal('Friday lunch', '2026-10-02');
        $used = $this->grant($lunch);
        $unused = $this->grant($lunch, 'Bar');
        $this->grant($this->meal('Friday dinner', '2026-10-02', 'Dinner', '17:30', '19:30'));
        $this->grant($this->meal('Sunday breakfast', '2026-10-04', 'Breakfast', '07:00', '09:30'));
        $this->grant($this->meal('Thursday dinner', '2026-10-01', 'Dinner', '17:30', '19:30'));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $used->id);

        $data = $this->mealData();
        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-04'], array_column($data['days'], 'date'));
        $friday = $data['days'][1];
        $this->assertCount(3, $friday['rows']);
        $lunchRows = array_values(array_filter($friday['rows'], fn ($row) => $row['meal_id'] === $lunch->id));
        $this->assertCount(2, $lunchRows);
        $this->assertEqualsCanonicalizing([$used->id, $unused->id], array_column($lunchRows, 'assignment_id'));
        $usedRow = collect($lunchRows)->firstWhere('used', true);
        $this->assertSame('13:05', $usedRow['used_at']);
        $this->assertFalse($usedRow['source_removed']);
        $this->assertSame('2026-10-02T09:00', $usedRow['shift_start']);
        $this->assertSame(['type_id' => $lunch->meal_type_id, 'type' => 'Lunch', 'total' => 2, 'used' => 1], $friday['counts'][0]);
        $this->assertSame(1, $friday['counts'][1]['total']);
        $this->assertSame(0, $friday['counts'][1]['used']);
        $this->assertArrayNotHasKey('claim_token', $usedRow);
        $this->assertSame(3, app(MealEntitlementQuery::class)->forPerson($this->event, $this->member->id)->count());
    }

    public function test_used_meal_is_kept_when_its_recipient_is_removed_but_the_meal_row_remains(): void
    {
        $assignment = $this->grant($this->meal('Friday lunch', '2026-10-02'));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $assignment->id);
        $unused = $this->grant($this->meal('Friday dinner', '2026-10-02', 'Dinner', '17:30', '19:30'));
        $assignment->update(['is_active' => false]);
        $unused->update(['is_active' => false]);

        $this->assertNotNull($assignment->fresh()->shift_meal_id);
        $data = $this->mealData();
        $this->assertCount(1, $data['days'][0]['rows']);
        $this->assertTrue($data['days'][0]['rows'][0]['source_removed']);
        $this->assertTrue($data['days'][0]['rows'][0]['used']);
        $this->assertSame(1, $data['days'][0]['counts'][0]['used']);
    }

    public function test_used_snapshots_survive_assignment_meal_row_and_shift_deletion_and_later_meal_edits(): void
    {
        foreach (['recipient', 'meal_row', 'shift'] as $removed) {
            $meal = $this->meal('Friday lunch '.$removed, '2026-10-02');
            $assignment = $this->grant($meal);
            app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $assignment->id, confirmed: true);
            match ($removed) {
                'recipient' => ShiftAssignment::findOrFail($assignment->shift_assignment_id)->delete(),
                'meal_row' => ShiftMeal::findOrFail($assignment->shift_meal_id)->delete(),
                'shift' => Shift::findOrFail($assignment->source_shift_id)->delete(),
            };
        }
        $data = $this->mealData();
        $this->assertCount(3, $data['days'][0]['rows']);
        foreach ($data['days'][0]['rows'] as $row) {
            $this->assertTrue($row['source_removed']);
            $this->assertTrue($row['used']);
            $this->assertSame('13:05', $row['used_at']);
        }
        app(MealService::class)->update($this->event, $meal, [
            'name' => 'Changed dinner', 'meal_type_id' => $meal->meal_type_id,
            'date' => '2026-10-04', 'starts_at' => '18:00', 'ends_at' => '20:00',
        ]);
        $snapshot = collect($this->mealData()['days'][0]['rows'])->firstWhere('assignment_id', $assignment->id);
        $this->assertSame('Friday lunch shift', $snapshot['name']);
        $this->assertSame('2026-10-02', $snapshot['date']);
        $this->assertSame('12:00', $snapshot['starts_at']);
    }

    public function test_overnight_meals_keep_the_starting_day_even_when_claimed_after_midnight(): void
    {
        $meal = $this->meal('Thursday midnight', '2026-10-01', 'Midnight', '23:30', '00:30');
        $assignment = $this->grant($meal);
        $this->travelTo(Carbon::parse('2026-10-02 00:12', $this->event->timezone));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $assignment->id);

        $row = $this->mealData()['days'][0]['rows'][0];
        $this->assertSame('2026-10-01', $row['date']);
        $this->assertSame('23:30', $row['starts_at']);
        $this->assertSame('00:30', $row['ends_at']);
        $this->assertSame('00:12', $row['used_at']);
    }

    public function test_direct_assignments_follow_the_same_rows_and_counts_as_kitchen(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $direct = app(MealAssignmentService::class)->assign($this->event, $this->member, $meal);
        $this->grant($meal);
        $data = $this->mealData();
        $row = collect($data['days'][0]['rows'])->firstWhere('assignment_id', $direct->id);
        $this->assertNull($row['source_shift_id']);
        $this->assertNull($row['shift_start']);
        $this->assertFalse($row['source_removed']);
        $this->assertFalse($row['used']);
        $this->assertSame(2, $data['days'][0]['counts'][0]['total']);
        $this->assertSame(2, (int) app(MealEntitlementQuery::class)->personCounts($this->event, $this->member->id)->sole()->total);
    }

    public function test_assignments_are_scoped_to_the_event_and_member(): void
    {
        $this->grant($this->meal('Ava lunch', '2026-10-02'));
        $other = $this->event->teamEngagements()->create([
            'person_id' => Person::create(['name' => 'Noah Park', 'email' => 'noah@example.test'])->id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        app(MealAssignmentService::class)->assign($this->event, $other, $this->meal('Noah dinner', '2026-10-02', 'Dinner', '17:30', '19:30'));
        $foreign = app(EventService::class)->create([
            'name' => 'Other festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'UTC',
        ]);
        $foreignMember = $foreign->teamEngagements()->create([
            'person_id' => $this->member->person_id, 'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $foreignMeal = app(MealService::class)->create($foreign, [
            'name' => 'Foreign lunch', 'meal_type_id' => $foreign->mealTypes()->where('name', 'Lunch')->sole()->id,
            'date' => '2026-10-02', 'starts_at' => '12:00', 'ends_at' => '14:00',
        ]);
        app(MealAssignmentService::class)->assign($foreign, $foreignMember, $foreignMeal);

        $this->assertCount(1, $this->mealData()['days'][0]['rows']);
        $this->assertSame('Ava lunch', $this->mealData()['days'][0]['rows'][0]['name']);
        $this->assertSame(0, app(MealEntitlementQuery::class)->forPersonAcrossEvent($this->event, $foreignMember->id)->count());
    }

    public function test_empty_hired_member_has_an_empty_meals_card(): void
    {
        $this->assertSame([], $this->mealData()['days']);
    }

    public function test_complete_day_is_returned_even_when_it_exceeds_ten_rows(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        for ($index = 0; $index < 12; $index++) {
            $this->grant($meal);
        }
        $day = $this->mealData()['days'][0];
        $this->assertCount(12, $day['rows']);
        $this->assertSame(12, $day['counts'][0]['total']);
    }

    public function test_meals_permission_is_required_and_rechecked_on_each_request(): void
    {
        $this->grant($this->meal('Friday lunch', '2026-10-02'));
        $viewer = $this->grantRoleAccess(User::factory()->create(), ['team.view', 'meals.view']);
        $viewer->setCurrentEvent($this->event);
        $this->actingAs($viewer);
        $this->assertCount(1, $this->mealData()['days']);
        $viewer->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role->update(['permissions' => ['team.view']]);
        $this->mock(MealEntitlementQuery::class)->shouldNotReceive('forPersonAcrossEvent');
        $this->get(route('team.members.show', $this->member))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canReadMeals', false)->missing('meals'));
    }

    public function test_meals_permission_does_not_grant_team_page_access(): void
    {
        $viewer = $this->grantRoleAccess(User::factory()->create(), ['meals.view']);
        $viewer->setCurrentEvent($this->event);
        $this->actingAs($viewer)->get(route('team.members.show', $this->member))->assertForbidden();
    }

    public function test_saved_nonhired_members_do_not_load_meal_data(): void
    {
        $this->grant($this->meal('Friday lunch', '2026-10-02'));
        $this->mock(MealEntitlementQuery::class)->shouldNotReceive('forPersonAcrossEvent');
        foreach (['applied', 'reviewing', 'declined'] as $status) {
            $this->member->update(['status' => $status]);
            $this->get(route('team.members.show', $this->member))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canReadMeals', false)->missing('meals'));
        }
    }

    public function test_locked_event_keeps_meals_visible(): void
    {
        $this->grant($this->meal('Friday lunch', '2026-10-02'));
        $this->event->lock();
        $this->assertCount(1, $this->mealData()['days']);
        $this->get(route('team.members.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
    }

    public function test_foreign_event_member_page_is_not_available(): void
    {
        $foreign = app(EventService::class)->create([
            'name' => 'Other festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'UTC',
        ]);
        $member = $foreign->teamEngagements()->create([
            'person_id' => $this->member->person_id, 'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $this->get(route('team.members.show', $member))->assertNotFound();
    }

    private function mealData(): array
    {
        $response = $this->get(route('team.members.show', $this->member))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Team/Member')->where('canReadMeals', true)->has('meals.days'));

        return $response->inertiaProps('meals');
    }

    private function meal(string $name, string $date, string $type = 'Lunch', string $start = '12:00', string $end = '14:00'): Meal
    {
        return app(MealService::class)->create($this->event, [
            'name' => $name, 'meal_type_id' => $this->event->mealTypes()->where('name', $type)->sole()->id,
            'date' => $date, 'starts_at' => $start, 'ends_at' => $end,
        ]);
    }

    private function grant(Meal $meal, string $location = 'Gate'): MealAssignment
    {
        $start = Carbon::parse($meal->date->toDateString().' 09:00', $this->event->timezone)->utc();
        $shift = $this->event->shifts()->create([
            'name' => $location.' shift',
            'location_id' => $this->event->locations()->firstOrCreate(['name' => $location])->id,
            'starts_at' => $start, 'ends_at' => $start->copy()->addHours(12),
        ]);
        $recipient = $shift->assignments()->create([
            'team_engagement_id' => $this->member->id, 'role_id' => Role::firstOrCreate(['name' => 'Crew'])->id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at,
        ]);
        $row = $shift->meals()->create(['meal_id' => $meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($row, [$recipient->id]);

        return MealAssignment::query()->where('shift_meal_id', $row->id)->sole();
    }
}
