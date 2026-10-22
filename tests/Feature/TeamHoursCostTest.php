<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamHoursCostTest extends TestCase
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

    public function test_costs_combine_same_location_minutes_before_team_totals_and_round_person_shift_rows_before_shift_totals(): void
    {
        $paid = $this->member('paid', '0.30');
        $first = $this->shift('2026-10-01 08:00:00', '2026-10-01 08:01:00');
        $second = $this->shift('2026-10-01 09:00:00', '2026-10-01 09:01:00');
        $this->assign($first, $paid);
        $this->assign($second, $paid);
        $query = app(TeamScheduledHoursQuery::class);
        $this->assertSame(1, $query->groups($this->event)->sole()['scheduled_cost_cents']);
        $this->getJson(route('team.reports.groups.people', $this->group))->assertOk()
            ->assertJsonPath('data.people.0.hourly_pay', '0.30')
            ->assertJsonPath('data.people.0.scheduled_minutes', 2)
            ->assertJsonPath('data.people.0.scheduled_cost_cents', 1)
            ->assertJsonPath('data.totals.scheduled_cost_cents', 1);
        $shifts = $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01']))->assertOk()->json('data.shifts');
        $this->assertSame([1, 1], array_column($shifts, 'scheduled_cost_cents'));
        $this->assertSame(2, array_sum(array_column($shifts, 'scheduled_cost_cents')));
        foreach ([$first, $second] as $shift) {
            $this->getJson(route('team.reports.shifts.people', $shift))->assertOk()
                ->assertJsonPath('data.people.0.scheduled_cost_cents', 1);
        }
        $this->assertSame(['scheduled_cost_cents' => 1, 'approved_cost_cents' => null, 'missing_rate_count' => 0], $query->eventCostTotals($this->event));
    }

    public function test_subcent_rows_sum_to_zero_and_exact_halves_round_up_on_both_databases(): void
    {
        $shift = $this->shift();
        foreach (range(1, 3) as $i) {
            $this->assign($shift, $this->member('paid', '0.24'));
        }
        $this->getJson(route('team.reports.data'))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 0);
        $this->assign($shift, $this->member('paid', '0.30'));
        $this->getJson(route('team.reports.data'))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 1);
        $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01']))->assertOk()->assertJsonPath('data.shifts.0.scheduled_cost_cents', 1);
    }

    public function test_current_rates_apply_to_past_hours_after_own_breaks_and_budget_ignores_date_selections(): void
    {
        $paid = $this->member('paid', '24.50');
        $first = $this->shift('2026-09-30 22:00:00', '2026-10-01 01:00:00');
        $assignment = $this->assign($first, $paid, '2026-10-01 00:00:00', '2026-10-01 00:45:00');
        $assignment->breaks()->create(['duration_minutes' => 15, 'starts_at' => '2026-10-01 00:15:00', 'sort_order' => 0]);
        $this->assign($this->shift('2026-10-05 09:00:00', '2026-10-05 10:00:00'), $paid);
        $range = ['start_date' => '2026-09-30', 'end_date' => '2026-09-30'];
        $this->getJson(route('team.reports.data', $range))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 1225);
        $this->assertSame(3675, app(TeamScheduledHoursQuery::class)->eventCostTotals($this->event)['scheduled_cost_cents']);
        $paid->update(['hourly_pay' => '25.00']);
        $this->getJson(route('team.reports.data', $range))->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 1250);
        $this->getJson(route('team.reports.data', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01']))
            ->assertOk()->assertJsonPath('data.totals.scheduled_cost_cents', 0);
        $this->assertSame(3750, app(TeamScheduledHoursQuery::class)->eventCostTotals($this->event)['scheduled_cost_cents']);
    }

    public function test_filters_keep_eligible_zero_hour_members_missing_and_zero_rates_and_hide_unmatched_teams_and_shifts(): void
    {
        $paid = $this->member('paid', null);
        $zero = $this->member('paid', '0.00');
        $volunteer = $this->member('volunteer', '999.00'); // Ignore stale legacy volunteer pay.
        $onlyVolunteers = $this->event->groups()->create(['name' => 'Volunteer team']);
        $volunteer->update(['group_id' => $onlyVolunteers->id]);
        $excluded = $this->member('paid', null, 'applied');
        $paidShift = $this->shift();
        $volunteerShift = $this->shift('2026-10-01 09:00:00', '2026-10-01 09:01:00');
        $empty = $this->shift('2026-10-01 10:00:00', '2026-10-01 10:01:00');
        $this->assign($paidShift, $paid);
        $this->assign($volunteerShift, $volunteer);
        $paid->update(['status' => 'declined']);
        $this->getJson(route('team.reports.data'))->assertOk()
            ->assertJsonPath('data.totals.scheduled_minutes', 2)->assertJsonPath('data.totals.scheduled_cost_cents', 0)
            ->assertJsonPath('data.totals.missing_rate_count', 1);
        $this->getJson(route('team.reports.data', ['employment_type' => 'paid']))->assertOk()
            ->assertJsonCount(1, 'data.groups')->assertJsonPath('data.groups.0.group_id', $this->group->id)
            ->assertJsonPath('data.totals.scheduled_minutes', 1);
        $data = $this->getJson(route('team.reports.groups.people', ['group' => $this->group, 'employment_type' => 'paid']))
            ->assertOk()->assertJsonCount(2, 'data.people')->assertJsonPath('data.totals.missing_rate_count', 1)->json('data.people');
        $people = collect($data)->keyBy('team_engagement_id');
        $this->assertNull($people[$paid->id]['hourly_pay']);
        $this->assertSame('0.00', $people[$zero->id]['hourly_pay']);
        $this->assertArrayNotHasKey($excluded->id, $people->all());
        $this->getJson(route('team.reports.groups.people', ['group' => $onlyVolunteers, 'employment_type' => 'volunteer']))
            ->assertOk()->assertJsonPath('data.people.0.hourly_pay', null)->assertJsonPath('data.people.0.scheduled_cost_cents', null)
            ->assertJsonPath('data.totals.scheduled_minutes', 1)->assertJsonPath('data.totals.scheduled_cost_cents', 0);
        foreach (['paid' => $paidShift, 'volunteer' => $volunteerShift] as $type => $shift) {
            $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01', 'employment_type' => $type]))
                ->assertOk()->assertJsonCount(1, 'data.shifts')->assertJsonPath('data.shifts.0.shift_id', $shift->id);
            $this->getJson(route('team.reports.shifts.people', ['shift' => $shift, 'employment_type' => $type]))->assertOk()->assertJsonCount(1, 'data.people');
        }
        $all = $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01']))->assertOk()->json('data.shifts');
        $this->assertCount(3, $all);
        $this->assertSame(0, collect($all)->firstWhere('shift_id', $empty->id)['scheduled_cost_cents']);
    }

    public function test_no_rate_totals_count_distinct_eligible_people_even_at_zero_hours(): void
    {
        $paid = $this->member('paid', null);
        $this->member('paid', null);
        foreach (['08', '09'] as $hour) {
            $this->assign($this->shift("2026-10-01 {$hour}:00:00", "2026-10-01 {$hour}:01:00"), $paid);
        }
        $this->getJson(route('team.reports.data'))->assertOk()->assertJsonPath('data.totals.missing_rate_count', 2);
        $this->getJson(route('team.reports.groups.people', $this->group))->assertOk()->assertJsonPath('data.totals.missing_rate_count', 2);
        $this->assertSame(2, app(TeamScheduledHoursQuery::class)->eventCostTotals($this->event)['missing_rate_count']);
    }

    public function test_report_permission_exposes_pay_without_personal_info_and_locked_events_remain_readable(): void
    {
        $paid = $this->member('paid', '24.50');
        $shift = $this->shift();
        $this->assign($shift, $paid);
        $this->user = $this->grantRoleAccess($this->user, ['team.hours_report.view', 'team.view']);
        $this->actingAs($this->user);
        $this->event->lock();
        foreach ([route('team.reports.groups.people', $this->group), route('team.reports.shifts.people', $shift)] as $url) {
            $response = $this->getJson($url)->assertOk();
            $row = collect($response->json('data.people'))->firstWhere('team_engagement_id', $paid->id);
            $this->assertSame('24.50', $row['hourly_pay']);
            $this->assertSame(41, $row['scheduled_cost_cents']);
            $this->assertNull($row['approved_cost_cents']);
            $this->assertStringNotContainsString('unapproved_cost', $response->getContent());
            $this->assertStringNotContainsString('"email"', $response->getContent());
        }
        $this->get(route('team.members.show', $paid))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('engagement.personal_info_hidden', true)->missing('engagement.hourly_pay'));
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->actingAs($this->user->fresh());
        foreach ([route('team.reports'), route('team.reports.data'), route('team.reports.groups.people', $this->group), route('team.reports.shifts', ['date' => '2026-10-01']), route('team.reports.shifts.people', $shift)] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_all_report_requests_validate_employment_filters_and_return_no_unapproved_cost(): void
    {
        $shift = $this->shift();
        $this->assign($shift, $this->member('paid', '10.00'));
        foreach ([route('team.reports'), route('team.reports.data'), route('team.reports.groups.people', $this->group), route('team.reports.shifts', ['date' => '2026-10-01']), route('team.reports.shifts.people', $shift)] as $url) {
            foreach (['staff', ['paid'], ''] as $bad) {
                $this->getJson($url.(str_contains($url, '?') ? '&' : '?').http_build_query(['employment_type' => $bad]))
                    ->assertUnprocessable()->assertJsonValidationErrors('employment_type');
            }
            if ($url !== route('team.reports')) {
                $response = $this->getJson($url)->assertOk();
                $this->assertStringNotContainsString('unapproved_cost', $response->getContent());
            }
        }
    }

    private function member(string $type, ?string $rate, string $status = 'hired'): TeamEngagement
    {
        return $this->createTeamEngagement(['event_id' => $this->event->id, 'group_id' => $this->group->id,
            'person_id' => Person::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail()])->id,
            'status' => $status, 'employment_type' => $type, 'hourly_pay' => $rate]);
    }

    private function shift(string $start = '2026-10-01 08:00:00', string $end = '2026-10-01 08:01:00'): Shift
    {
        $location = $this->event->locations()->firstOrCreate(['name' => 'Gate']);

        return $this->event->shifts()->create(['location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end]);
    }

    private function assign(Shift $shift, TeamEngagement $member, ?string $start = null, ?string $end = null): ShiftAssignment
    {
        return $shift->assignments()->create(['team_engagement_id' => $member->id, 'starts_at' => $start ?? $shift->starts_at, 'ends_at' => $end ?? $shift->ends_at]);
    }
}
