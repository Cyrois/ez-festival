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
use App\Services\EventService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealOverrideService;
use App\Services\MealService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $this->member = $this->event->teamEngagements()->create([
            'person_id' => Person::create(['name' => 'Ava Lee', 'email' => 'ava@example.test'])->id,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);
        $this->travelTo(Carbon::parse('2026-10-02 13:05', $this->event->timezone));
    }

    public function test_empty_event_has_no_rows_and_an_authorized_export_has_only_the_header(): void
    {
        $this->assertSame([], $this->reportRows());
        $this->assertSame("Meal,Date,\"Meal type\",Projected,Used,Remaining,Extras,Total\n", $this->get(route('reports.meals.export'))->assertOk()->streamedContent());
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

        foreach ([$used, $unused] as $grant) {
            ShiftMeal::findOrFail($grant->shift_meal_id)->delete();
            ShiftAssignment::findOrFail($grant->shift_assignment_id)->delete();
            $this->event->shifts()->findOrFail($grant->source_shift_id)->delete();
        }
        $this->assertSame([0, 1, 0, 0, -1], $this->counts($this->reportRows()[0]));
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
        $this->assertSame([1, 2, 0, 1, -2], $this->counts($rows[0]));
        $this->assertSame([1, 1, 0, 0, 0], $this->counts($rows[1]));
    }

    public function test_overrides_are_a_used_subset_and_unclaim_and_reclaim_update_counts_without_changing_projected(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $normal = app(MealAssignmentService::class)->assign($this->event, $this->member, $meal);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $normal->id);
        $override = app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $meal->id);
        $this->assertSame([1, 2, 0, 1, -2], $this->counts($this->reportRows()[0]));
        app(MealClaimService::class)->unclaim($this->event, $this->member, $this->user, $override['assignment_id'], $override['claim_token']);
        $this->assertSame([1, 1, 0, 0, 0], $this->counts($this->reportRows()[0]));
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $override['assignment_id'], true);
        $this->assertSame([1, 2, 0, 1, -2], $this->counts($this->reportRows()[0]));
    }

    public function test_remaining_is_clamped_but_total_preserves_negative_values_without_summary_rows(): void
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

    public function test_csv_matches_all_rows_uses_iso_dates_and_escapes_type_names(): void
    {
        $type = $this->event->mealTypes()->where('name', 'Lunch')->sole();
        $type->update(['name' => "Lunch, \"late\"\ncrew"]);
        $meal = app(MealService::class)->create($this->event, [
            'name' => 'Friday lunch', 'meal_type_id' => $type->id, 'date' => '2026-10-02', 'starts_at' => '12:00', 'ends_at' => '14:00',
        ]);
        $this->grant($meal);
        $this->meal('Sunday dinner', '2026-10-04', 'Dinner');
        $rows = $this->reportRows();
        $response = $this->get(route('reports.meals.export'))->assertOk();
        $response->assertDownload('meal-report-sunrise-folk-fest-2026.csv');
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $csv = $this->csvRows($response->streamedContent());
        $this->assertSame(['Meal', 'Date', 'Meal type', 'Projected', 'Used', 'Remaining', 'Extras', 'Total'], array_shift($csv));
        $this->assertCount(count($rows), $csv);
        foreach ($rows as $index => $row) {
            $this->assertSame(array_map('strval', $this->counts($row)), array_slice($csv[$index], 3));
            $this->assertSame($row['date'], $csv[$index][1]);
            $this->assertSame($row['meal_name'], $csv[$index][0]);
            $this->assertSame($row['meal_type'], $csv[$index][2]);
        }
    }

    public function test_export_treats_formula_prefixes_in_type_names_as_text(): void
    {
        $meal = $this->meal('Friday lunch', '2026-10-02');
        $meal->update(['name' => '+1+1']);
        $meal->mealType->update(['name' => '=1+1']);
        $csv = $this->csvRows($this->get(route('reports.meals.export'))->streamedContent());
        $this->assertSame("'+1+1", $csv[1][0]);
        $this->assertSame("'=1+1", $csv[1][2]);
    }

    public function test_current_event_scopes_all_configured_projected_and_used_rows_and_export(): void
    {
        $this->grant($this->meal('Friday lunch', '2026-10-02'));
        $foreign = app(EventService::class)->create([
            'name' => 'Other festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'UTC',
        ]);
        $foreignMember = $foreign->teamEngagements()->create([
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
        $this->assertStringNotContainsString('Dinner', $this->get(route('reports.meals.export'))->streamedContent());
    }

    public function test_view_meals_is_sufficient_and_revocation_refuses_page_and_export(): void
    {
        $this->meal('Friday lunch', '2026-10-02');
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->assertCount(1, $this->reportRows());
        $this->get(route('reports.meals.export'))->assertOk();
        $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role->update(['permissions' => ['team.view']]);
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('reports.meals.export'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('permissions', fn ($permissions) => $permissions['meals.view'] === false)->missing('meals'));
    }

    public function test_locked_event_is_readable_by_view_only_roles_and_admins(): void
    {
        $this->meal('Friday lunch', '2026-10-02');
        $this->event->lock();
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->assertCount(1, $this->reportRows());
        $this->get(route('reports.meals.export'))->assertOk();
        $this->grantAdminAccess($this->user);
        $this->assertCount(1, $this->reportRows());
        $this->get(route('reports.meals.export'))->assertOk();
    }

    private function reportRows(): array
    {
        $response = $this->get(route('reports.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->has('meals.rows'));

        return $response->inertiaProps('meals')['rows'];
    }

    private function counts(array $row): array
    {
        return [$row['projected'], $row['used'], $row['remaining'], $row['extras'], $row['total']];
    }

    private function csvRows(string $content): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
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
