<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Location;
use App\Models\Person;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamLocationHoursReportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Group $group;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        $this->group = $this->event->groups()->create(['name' => 'Gate']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = $this->grantAdminAccess(User::factory()->create());
        $this->user->setCurrentEvent($this->event);
        $this->user->ensureOrganization();
        $this->actingAs($this->user);
    }

    public function test_assignments_split_by_shift_location_and_start_day_with_full_location_rosters(): void
    {
        $gate = $this->location('Gate');
        $stage = $this->location('Stage');
        $this->location('Unused');
        $paid = $this->member('paid', '24.50');
        $volunteer = $this->member('volunteer', '100.00');
        $unassigned = $this->member('paid', '50.00');
        $paid->update(['status' => 'declined']);
        $overnight = $this->assign($gate, $paid, '2026-09-30 23:00:00', '2026-10-01 01:00:00');
        $overnight->breaks()->create(['duration_minutes' => 30, 'starts_at' => '2026-10-01 00:00:00', 'sort_order' => 0]);
        $this->assign($stage, $paid, '2026-09-30 18:00:00', '2026-09-30 19:00:00');
        $this->assign($gate, $volunteer, '2026-09-30 19:00:00', '2026-09-30 20:00:00');
        $this->assign($gate, $paid, '2026-10-01 10:00:00', '2026-10-01 12:00:00');
        $range = ['start_date' => '2026-09-30', 'end_date' => '2026-09-30'];
        $response = $this->getJson(route('team.reports.locations', $range))->assertOk()
            ->assertJsonCount(2, 'data.locations')->assertJsonPath('data.totals.scheduled_minutes', 210)
            ->assertJsonPath('data.totals.scheduled_cost_cents', 6125)
            ->assertJsonPath('data.totals.approved_cost_cents', null);
        $rows = collect($response->json('data.locations'))->keyBy('location_id');
        $this->assertSame(150, $rows[$gate->id]['scheduled_minutes']);
        $this->assertSame(3675, $rows[$gate->id]['scheduled_cost_cents']);
        $this->assertSame(60, $rows[$stage->id]['scheduled_minutes']);
        foreach ([$gate, $stage] as $location) {
            $people = $this->getJson(route('team.reports.locations.people', ['location' => $location, ...$range]))->assertOk();
            $this->assertSame($rows[$location->id]['scheduled_minutes'], $people->json('data.totals.scheduled_minutes'));
            $this->assertSame($rows[$location->id]['scheduled_cost_cents'], $people->json('data.totals.scheduled_cost_cents'));
            $this->assertSame($people->json('data.totals.scheduled_minutes'), array_sum(array_column($people->json('data.people'), 'scheduled_minutes')));
            $this->assertNotContains($unassigned->id, array_column($people->json('data.people'), 'team_engagement_id'));
            $this->assertStringNotContainsString('unapproved_cost', $people->getContent());
        }
        $this->getJson(route('team.reports.locations', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01']))
            ->assertOk()->assertJsonCount(1, 'data.locations')->assertJsonPath('data.totals.scheduled_minutes', 120);
    }

    public function test_subcent_person_location_costs_round_half_up_before_location_and_event_totals(): void
    {
        $location = $this->location('Gate');
        foreach (['0.30', '0.24'] as $rate) {
            $this->assign($location, $this->member('paid', $rate), '2026-10-01 08:00:00', '2026-10-01 08:01:00');
        }
        $this->getJson(route('team.reports.locations'))->assertOk()
            ->assertJsonPath('data.locations.0.scheduled_cost_cents', 1)->assertJsonPath('data.totals.scheduled_cost_cents', 1);
        $people = $this->getJson(route('team.reports.locations.people', $location))->assertOk()->json('data.people');
        $this->assertSame([0, 1], collect($people)->pluck('scheduled_cost_cents')->sort()->values()->all());
    }

    public function test_summary_team_and_budget_sum_rounded_person_location_costs_for_the_selected_filters(): void
    {
        $gate = $this->location('Gate');
        $stage = $this->location('Stage');
        $paid = $this->member('paid', '0.30');
        $belowHalf = $this->member('paid', '0.24');
        $volunteer = $this->member('volunteer', null);
        foreach ([$paid, $belowHalf, $volunteer] as $member) {
            $this->assign($gate, $member, '2026-10-01 08:00:00', '2026-10-01 08:01:00');
            $this->assign($stage, $member, '2026-10-02 08:00:00', '2026-10-02 08:01:00');
        }

        foreach (['all' => 2, 'paid' => 2, 'volunteer' => 0] as $filter => $expectedCents) {
            foreach ([[], ['start_date' => '2026-10-01', 'end_date' => '2026-10-02']] as $range) {
                $params = ['employment_type' => $filter, ...$range];
                $locations = $this->getJson(route('team.reports.locations', $params))->assertOk()
                    ->assertJsonPath('data.totals.scheduled_cost_cents', $expectedCents)->json('data.locations');
                $this->assertSame(array_fill(0, 2, intdiv($expectedCents, 2)), array_column($locations, 'scheduled_cost_cents'));
                $this->getJson(route('team.reports.data', $params))->assertOk()
                    ->assertJsonPath('data.totals.scheduled_cost_cents', $expectedCents)
                    ->assertJsonPath('data.groups.0.scheduled_cost_cents', $expectedCents);
                $people = $this->getJson(route('team.reports.groups.people', ['group' => $this->group, ...$params]))->assertOk()
                    ->assertJsonPath('data.totals.scheduled_cost_cents', $expectedCents)->json('data.people');
                if ($filter !== 'volunteer') {
                    $this->assertSame(2, collect($people)->firstWhere('team_engagement_id', $paid->id)['scheduled_cost_cents']);
                    $this->assertSame(0, collect($people)->firstWhere('team_engagement_id', $belowHalf->id)['scheduled_cost_cents']);
                }
            }
        }
        foreach (['2026-10-01' => $gate, '2026-10-02' => $stage] as $day => $location) {
            $range = ['start_date' => $day, 'end_date' => $day, 'employment_type' => 'paid'];
            $this->getJson(route('team.reports.data', $range))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 1);
            $this->getJson(route('team.reports.locations', $range))->assertOk()
                ->assertJsonCount(1, 'data.locations')->assertJsonPath('data.locations.0.location_id', $location->id)
                ->assertJsonPath('data.totals.scheduled_cost_cents', 1);
            $this->getJson(route('team.reports.locations.people', ['location' => $location, ...$range]))->assertOk()
                ->assertJsonPath('data.totals.scheduled_cost_cents', 1);
        }
        $this->assertSame(2, app(TeamScheduledHoursQuery::class)->eventCostTotals($this->event)['scheduled_cost_cents']);
    }

    public function test_filters_hide_zero_hour_and_unmatched_locations_and_count_distinct_missing_rates(): void
    {
        $gate = $this->location('Gate');
        $stage = $this->location('Stage');
        $volunteers = $this->location('Volunteers');
        $zero = $this->location('Zero');
        $missing = $this->member('paid', null);
        $volunteer = $this->member('volunteer', '100.00');
        foreach ([$gate, $stage] as $location) {
            $this->assign($location, $missing);
        }
        $this->assign($volunteers, $volunteer);
        $zeroHours = $this->assign($zero, $missing);
        $zeroHours->breaks()->create(['duration_minutes' => 60, 'starts_at' => '2026-10-01 08:00:00', 'sort_order' => 0]);
        $this->getJson(route('team.reports.locations'))->assertOk()->assertJsonCount(3, 'data.locations')
            ->assertJsonPath('data.totals.missing_rate_count', 1)->assertJsonPath('data.totals.scheduled_cost_cents', 0);
        $this->getJson(route('team.reports.locations', ['employment_type' => 'paid']))->assertOk()
            ->assertJsonCount(2, 'data.locations')->assertJsonPath('data.totals.scheduled_minutes', 120)
            ->assertJsonPath('data.totals.missing_rate_count', 1);
        $this->getJson(route('team.reports.locations', ['employment_type' => 'volunteer']))->assertOk()
            ->assertJsonCount(1, 'data.locations')->assertJsonPath('data.locations.0.location_id', $volunteers->id)
            ->assertJsonPath('data.totals.scheduled_minutes', 60)->assertJsonPath('data.totals.missing_rate_count', 0);
        $this->getJson(route('team.reports.locations.people', $volunteers))->assertOk()
            ->assertJsonPath('data.people.0.hourly_pay', null)->assertJsonPath('data.people.0.scheduled_cost_cents', null);
        $this->getJson(route('team.reports.locations.people', ['location' => $gate, 'employment_type' => 'volunteer']))
            ->assertOk()->assertJsonCount(0, 'data.people')->assertJsonPath('data.totals.scheduled_minutes', 0);
        // Assigned people remain visible even at zero hours; unassigned Hired people never join a location roster.
        $this->getJson(route('team.reports.locations.people', $zero))->assertOk()->assertJsonCount(1, 'data.people')
            ->assertJsonPath('data.people.0.scheduled_minutes', 0)->assertJsonPath('data.people.0.hourly_pay', null);
    }

    public function test_event_scoping_form_request_validation_and_location_deep_links(): void
    {
        $location = $this->location('Gate');
        $otherEvent = Event::create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $foreign = $otherEvent->locations()->create(['name' => 'Foreign']);
        $foreignGroup = $otherEvent->groups()->create(['name' => 'Foreign']);
        $foreignPerson = $this->member('paid', '99.00');
        $foreignPerson->update(['event_id' => $otherEvent->id, 'group_id' => $foreignGroup->id]);
        $shift = $otherEvent->shifts()->create(['location_id' => $foreign->id, 'starts_at' => '2026-10-01 08:00:00', 'ends_at' => '2026-10-01 09:00:00']);
        $shift->assignments()->create(['team_engagement_id' => $foreignPerson->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $this->getJson(route('team.reports.locations'))->assertOk()->assertJsonCount(0, 'data.locations');
        $this->getJson(route('team.reports.locations.people', $foreign))->assertNotFound();
        $this->getJson(route('team.reports', ['view' => 'location', 'location_id' => $foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('location_id');
        foreach ([route('team.reports.locations'), route('team.reports.locations.people', $location)] as $url) {
            foreach ([['start_date' => '2026-10-01'], ['start_date' => '2026-10-03', 'end_date' => '2026-10-01'], ['employment_type' => 'invalid']] as $bad) {
                $this->getJson($url.'?'.http_build_query($bad))->assertUnprocessable();
            }
        }
        $this->get(route('team.reports', ['view' => 'location', 'location_id' => $location->id, 'start_date' => '2026-09-01', 'end_date' => '2026-10-01', 'employment_type' => 'paid']))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shiftFilters.view', 'location')->where('filters.location_id', $location->id)
            ->where('filters.start_date', '2026-09-01')->where('filters.end_date', '2026-10-01')->where('filters.employment_type', 'paid')
            ->has('locationOptions', 1)->where('locationOptions.0.location_id', $location->id));
    }

    public function test_report_permission_controls_all_data_and_member_links_without_hiding_locked_event_pay(): void
    {
        $location = $this->location('Gate');
        $member = $this->member('paid', '24.50');
        $this->assign($location, $member);
        $this->event->lock();
        foreach ([false, true] as $canViewTeam) {
            $this->grantRoleAccess($this->user, ['team.hours_report.view', ...($canViewTeam ? ['team.view'] : [])]);
            $this->actingAs($this->user->fresh());
            $this->getJson(route('team.reports.locations'))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 2450);
            $this->getJson(route('team.reports.locations.people', $location))->assertOk()
                ->assertJsonPath('data.people.0.hourly_pay', '24.50')->assertJsonPath('data.people.0.approved_cost_cents', null)
                ->assertJsonPath('data.people.0.member_url', $canViewTeam ? route('team.members.show', $member) : null);
        }
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->actingAs($this->user->fresh());
        foreach ([route('team.reports', ['view' => 'location']), route('team.reports.locations'), route('team.reports.locations.people', $location)] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_location_query_count_is_constant_for_three_and_three_hundred_locations(): void
    {
        $member = $this->member('paid', '10.00');
        $query = app(TeamScheduledHoursQuery::class);
        $counts = [];
        foreach ([3, 300] as $target) {
            for ($i = $this->event->locations()->count(); $i < $target; $i++) {
                $this->assign($this->location('Location '.$i), $member);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertCount($target, $query->locations($this->event));
            $this->assertSame(0, $query->locationMissingRateCount($this->event));
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame([2, 2], $counts);
    }

    private function location(string $name): Location
    {
        return $this->event->locations()->create(['name' => $name]);
    }

    private function member(string $type, ?string $rate): TeamEngagement
    {
        return $this->createTeamEngagement(['event_id' => $this->event->id, 'group_id' => $this->group->id,
            'person_id' => Person::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail()])->id,
            'status' => 'hired', 'employment_type' => $type, 'hourly_pay' => $rate]);
    }

    private function assign(Location $location, TeamEngagement $member, string $start = '2026-10-01 08:00:00', string $end = '2026-10-01 09:00:00'): ShiftAssignment
    {
        $shift = $this->event->shifts()->create(['location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end]);

        return $shift->assignments()->create(['team_engagement_id' => $member->id, 'starts_at' => $start, 'ends_at' => $end]);
    }
}
