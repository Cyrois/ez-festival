<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\TeamEngagementService;
use App\Support\OrganizationContext;
use App\Support\ShiftAssignmentHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamHoursReportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Festival');
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->user->ensureOrganization();
        $this->actingAs($this->user);
    }

    public function test_initial_overview_reconciles_all_groups_including_zero_rows_without_detail_or_personal_data(): void
    {
        $stage = $this->event->groups()->create(['name' => 'Stage']);
        $gate = $this->event->groups()->create(['name' => 'Gate']);
        $night = $this->shift($this->event, '2026-09-30 22:00:00', '2026-10-01 06:00:00');
        $first = $this->assign($night, $gate, breaks: [15, 30]);
        $second = $this->assign($night, $stage, '2026-10-01 02:00:00', '2026-10-01 06:00:00', [30]);
        $this->assign($night, $stage, '2026-10-01 02:00:00', '2026-10-01 02:30:00', [60]);
        for ($i = 0; $i < 26; $i++) {
            $this->event->groups()->create(['name' => sprintf('Unused %02d', $i)]);
        }
        $first->teamEngagement->update(['status' => 'declined']);
        $expected = ShiftAssignmentHours::scheduledMinutes($first, 45) + ShiftAssignmentHours::scheduledMinutes($second, 30);
        $response = $this->getJson(route('team.reports.data'))->assertOk();
        $data = $response->json('data');
        $this->assertSame(['event_id', 'employment_type', 'range', 'totals', 'groups'], array_keys($data));
        $this->assertSame($this->event->id, $data['event_id']);
        $this->assertSame(['start_date' => null, 'end_date' => null], $data['range']);
        $this->assertSame(['scheduled_minutes' => $expected, 'approved_minutes' => null, 'unapproved_minutes' => null, 'scheduled_cost_cents' => 53750, 'approved_cost_cents' => null, 'missing_rate_count' => 0], $data['totals']);
        $this->assertCount(28, $data['groups']);
        $this->assertSame(['Gate', 'Stage', ...array_map(fn ($i) => sprintf('Unused %02d', $i), range(0, 25))], array_column($data['groups'], 'name'));
        $this->assertSame($expected, array_sum(array_column($data['groups'], 'scheduled_minutes')));
        foreach ($data['groups'] as $row) {
            $this->assertSame(['group_id', 'name', 'scheduled_minutes', 'approved_minutes', 'unapproved_minutes', 'scheduled_cost_cents', 'approved_cost_cents', 'missing_rate_count'], array_keys($row));
            $this->assertNull($row['approved_minutes']);
            $this->assertNull($row['unapproved_minutes']);
        }
        $this->assertSame(0, $data['groups'][27]['scheduled_minutes']);
        foreach (['people', 'days', 'shifts', 'assignments', 'hourly_pay'] as $key) {
            $response->assertJsonMissingPath('data.'.$key);
            $this->assertStringNotContainsString('"'.$key.'"', $response->getContent());
        }
    }

    public function test_free_date_range_is_inclusive_and_attributes_after_midnight_assignments_to_shift_start_day(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $night = $this->shift($this->event, '2026-09-30 22:00:00', '2026-10-01 06:00:00');
        $this->assign($night, $group, '2026-10-01 02:00:00', '2026-10-01 06:00:00', [30]);
        $this->assign($this->shift($this->event, '2026-10-03 23:00:00', '2026-10-04 01:00:00'), $group);
        $this->assign($this->shift($this->event, '2026-10-04 00:00:00', '2026-10-04 01:00:00'), $group);
        foreach ([
            ['2026-09-30', '2026-09-30', 210],
            ['2026-10-01', '2026-10-01', 0],
            ['2026-09-30', '2026-10-03', 330],
            ['2026-10-04', '2026-10-04', 60],
            ['2027-01-01', '2027-01-02', 0],
        ] as [$start, $end, $expected]) {
            $this->getJson(route('team.reports.data', ['start_date' => $start, 'end_date' => $end]))->assertOk()
                ->assertJsonPath('data.range', ['start_date' => $start, 'end_date' => $end])
                ->assertJsonPath('data.totals.scheduled_minutes', $expected)
                ->assertJsonPath('data.groups.0.scheduled_minutes', $expected);
        }
        $this->getJson(route('team.reports.data', ['start_date' => '', 'end_date' => '']))->assertOk()
            ->assertJsonPath('data.totals.scheduled_minutes', 390)
            ->assertJsonPath('data.range', ['start_date' => null, 'end_date' => null]);
    }

    public function test_form_request_rejects_missing_malformed_and_reversed_date_bounds(): void
    {
        foreach ([
            [['start_date' => '2026-10-01'], 'end_date'],
            [['end_date' => '2026-10-01'], 'start_date'],
            [['start_date' => '2026-10-03', 'end_date' => '2026-10-01'], 'end_date'],
            [['start_date' => '2026-02-30', 'end_date' => '2026-10-01'], 'start_date'],
            [['start_date' => 'tomorrow', 'end_date' => '2026-10-01'], 'start_date'],
            [['start_date' => '2026-10-01', 'end_date' => '2026-10-01 12:00'], 'end_date'],
        ] as [$params, $field]) {
            $this->getJson(route('team.reports.data', $params))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->getJson(route('team.reports.data', ['start_date' => '2026-10-03', 'end_date' => '2026-10-01']))
            ->assertJsonPath('errors.end_date.0', __('team.reports.range_order'));
    }

    public function test_empty_event_has_zero_totals_and_no_synthetic_group(): void
    {
        $this->getJson(route('team.reports.data'))->assertOk()->assertExactJson(['data' => [
            'event_id' => $this->event->id,
            'employment_type' => 'all',
            'range' => ['start_date' => null, 'end_date' => null],
            'totals' => ['scheduled_minutes' => 0, 'approved_minutes' => null, 'unapproved_minutes' => null, 'scheduled_cost_cents' => 0, 'approved_cost_cents' => null, 'missing_rate_count' => 0],
            'groups' => [],
        ]]);
    }

    public function test_data_endpoint_requires_sign_in(): void
    {
        auth()->logout();
        $this->getJson(route('team.reports.data'))->assertUnauthorized();
    }

    public function test_report_scopes_to_current_event_and_ignores_foreign_event_query_parameters(): void
    {
        $foreign = $this->event('Other festival');
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $otherGroup = $foreign->groups()->create(['name' => 'Other group']);
        $this->assign($this->shift($this->event), $group);
        $this->assign($this->shift($foreign), $otherGroup);
        $this->getJson(route('team.reports.data', ['event_id' => $foreign->id, 'group_id' => $otherGroup->id]))->assertOk()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonCount(1, 'data.groups')->assertJsonPath('data.groups.0.group_id', $group->id);
        $this->user->setCurrentEvent($foreign);
        $this->getJson(route('team.reports.data'))->assertOk()
            ->assertJsonPath('data.event_id', $foreign->id)
            ->assertJsonCount(1, 'data.groups')->assertJsonPath('data.groups.0.group_id', $otherGroup->id);
    }

    public function test_http_query_count_is_constant_for_three_and_three_hundred_people(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $shift = $this->shift($this->event);
        $counts = [];
        foreach ([3, 300] as $size) {
            for ($i = ($size === 3 ? 0 : 3); $i < $size; $i++) {
                $this->assign($shift, $group);
            }
            $this->actingAs($this->user->fresh());
            $this->app->forgetScopedInstances();
            // Compare cold loads at both sizes, including the feature-flag lookup.
            Cache::flush();
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $this->getJson(route('team.reports.data'))->assertOk()
                    ->assertJsonPath('data.totals.scheduled_minutes', $size * 240);
                $queries = DB::getQueryLog();
                $counts[] = count($queries);
                $aggregates = array_filter($queries, fn ($query) => str_contains($query['query'], 'scheduled_minutes'));
                $this->assertCount(1, $aggregates);
                $sql = array_values($aggregates)[0]['query'];
                $this->assertDoesNotMatchRegularExpression('/"people"|"users"/i', $sql);
            } finally {
                DB::disableQueryLog();
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }

    public function test_group_people_includes_hired_and_assigned_members_and_reconciles_without_contact_or_other_details(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $otherGroup = $this->event->groups()->create(['name' => 'Stage']);
        $shift = $this->shift($this->event);
        $paid = $this->assign($shift, $group, breaks: [15, 30]);
        $paid->teamEngagement->person->update(['name' => 'Ava']);
        $paid->teamEngagement->update(['status' => 'declined']);
        $zero = $this->assign($shift, $group, '2026-10-01 08:00:00', '2026-10-01 08:30:00', [60]);
        $zero->teamEngagement->person->update(['name' => 'Dave']);
        $zero->teamEngagement->update(['status' => 'reviewing', 'employment_type' => 'volunteer', 'hourly_pay' => null]);
        $this->assign($shift, $otherGroup);
        foreach (['applied', 'reviewing', 'hired', 'declined'] as $status) {
            $this->createTeamEngagement(['event_id' => $this->event->id, 'group_id' => $group->id,
                'person_id' => Person::create(['name' => 'Unassigned '.$status, 'email' => fake()->unique()->safeEmail()])->id,
                'status' => $status, 'employment_type' => 'volunteer']);
        }
        for ($i = 0; $i < 26; $i++) {
            $this->createTeamEngagement(['event_id' => $this->event->id, 'group_id' => $group->id,
                'person_id' => Person::create(['name' => sprintf('Zero %02d', $i), 'email' => fake()->unique()->safeEmail()])->id,
                'status' => 'hired']);
        }
        $this->createTeamEngagement(['event_id' => $this->event->id,
            'person_id' => Person::create(['name' => 'Ungrouped applicant', 'email' => fake()->unique()->safeEmail()])->id]);

        $response = $this->getJson(route('team.reports.groups.people', $group))->assertOk();
        $data = $response->json('data');
        $this->assertSame(['event_id', 'employment_type', 'range', 'group', 'totals', 'people'], array_keys($data));
        $this->assertSame($this->event->id, $data['event_id']);
        $this->assertSame(['start_date' => null, 'end_date' => null], $data['range']);
        $this->assertSame(['group_id' => $group->id, 'name' => 'Gate'], $data['group']);
        $this->assertCount(29, $data['people']);
        $this->assertSame(['Ava', 'Dave', 'Unassigned hired',
            ...array_map(fn ($i) => sprintf('Zero %02d', $i), range(0, 25))], array_column($data['people'], 'name'));
        $this->assertSame(195, $data['totals']['scheduled_minutes']);
        $this->assertSame(195, array_sum(array_column($data['people'], 'scheduled_minutes')));
        $this->assertSame('paid', $data['people'][0]['employment_type']);
        $this->assertSame('volunteer', $data['people'][1]['employment_type']);
        foreach ($data['people'] as $index => $person) {
            $this->assertSame(['team_engagement_id', 'person_id', 'name', 'employment_type', 'hourly_pay', 'member_url', 'scheduled_minutes', 'approved_minutes', 'unapproved_minutes', 'scheduled_cost_cents', 'approved_cost_cents', 'missing_rate_count'], array_keys($person));
            $this->assertSame(route('team.members.show', $person['team_engagement_id']), $person['member_url']);
            $this->assertSame($index === 0 ? 195 : 0, $person['scheduled_minutes']);
            $this->assertNull($person['approved_minutes']);
            $this->assertNull($person['unapproved_minutes']);
        }
        $overview = $this->getJson(route('team.reports.data'))->assertOk()->json('data.groups');
        $this->assertSame(collect($overview)->firstWhere('group_id', $group->id)['scheduled_minutes'], $data['totals']['scheduled_minutes']);
        foreach (['email', 'phone', 'shifts', 'days', 'assignments'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $response->getContent());
        }
    }

    public function test_group_people_keeps_scheduled_hours_after_a_member_leaves_hired(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $assignment = $this->assign($this->shift($this->event), $group, breaks: [30]);
        $otherGroup = $this->event->groups()->create(['name' => 'Stage']);
        $this->assign($this->shift($this->event), $otherGroup);

        app(TeamEngagementService::class)->updateStatus($assignment->teamEngagement, 'declined');

        $this->assertSame('declined', $assignment->teamEngagement->fresh()->status);
        $this->getJson(route('team.reports.groups.people', $group))->assertOk()
            ->assertJsonCount(1, 'data.people')
            ->assertJsonPath('data.people.0.team_engagement_id', $assignment->team_engagement_id)
            ->assertJsonPath('data.people.0.scheduled_minutes', 210)
            ->assertJsonPath('data.totals.scheduled_minutes', 210);
        $this->getJson(route('team.reports.groups.people', $otherGroup))->assertOk()
            ->assertJsonCount(1, 'data.people')->assertJsonPath('data.totals.scheduled_minutes', 240);
        $overview = $this->getJson(route('team.reports.data'))->assertOk()->json('data');
        $this->assertSame(210, collect($overview['groups'])->firstWhere('group_id', $group->id)['scheduled_minutes']);
        $this->assertSame(450, $overview['totals']['scheduled_minutes']);
    }

    public function test_group_people_excludes_non_hired_members_with_only_out_of_range_assignments(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $night = $this->shift($this->event, '2026-09-30 22:00:00', '2026-10-01 06:00:00');
        $members = [];
        foreach (['applied', 'reviewing', 'hired', 'declined'] as $status) {
            $assignment = $this->assign($night, $group, '2026-10-01 02:00:00', '2026-10-01 06:00:00');
            $assignment->teamEngagement->update(['status' => $status]);
            $members[$status] = $assignment->team_engagement_id;
        }

        foreach ([[null, null, 4, 960], ['2026-09-30', '2026-09-30', 4, 960],
            ['2026-10-01', '2026-10-03', 1, 0]] as [$start, $end, $count, $minutes]) {
            $range = ['start_date' => $start, 'end_date' => $end];
            $people = $this->getJson(route('team.reports.groups.people', ['group' => $group, ...$range]))->assertOk()
                ->assertJsonCount($count, 'data.people')->assertJsonPath('data.totals.scheduled_minutes', $minutes)
                ->json('data.people');
            $this->assertEqualsCanonicalizing($count === 1 ? [$members['hired']] : array_values($members), array_column($people, 'team_engagement_id'));
            $this->assertSame($minutes, array_sum(array_column($people, 'scheduled_minutes')));
            $overview = $this->getJson(route('team.reports.data', $range))->assertOk()->json('data');
            $this->assertSame($minutes, $overview['groups'][0]['scheduled_minutes']);
            $this->assertSame($minutes, $overview['totals']['scheduled_minutes']);
        }
    }

    public function test_group_people_free_range_uses_shift_start_day_and_keeps_unassigned_members_at_zero(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $night = $this->shift($this->event, '2026-09-30 22:00:00', '2026-10-01 06:00:00');
        $assignment = $this->assign($night, $group, '2026-10-01 02:00:00', '2026-10-01 06:00:00', [30]);
        $later = $this->shift($this->event, '2026-10-04 08:00:00', '2026-10-04 10:00:00');
        $later->assignments()->create(['team_engagement_id' => $assignment->team_engagement_id,
            'starts_at' => $later->starts_at, 'ends_at' => $later->ends_at]);
        foreach ([['2026-09-30', '2026-09-30', 210], ['2026-10-01', '2026-10-03', 0],
            ['2026-09-30', '2026-10-04', 330], ['2026-10-04', '2026-10-04', 120]] as [$start, $end, $expected]) {
            $params = ['group' => $group, 'start_date' => $start, 'end_date' => $end];
            $this->getJson(route('team.reports.groups.people', $params))->assertOk()
                ->assertJsonCount(1, 'data.people')->assertJsonPath('data.people.0.scheduled_minutes', $expected)
                ->assertJsonPath('data.totals.scheduled_minutes', $expected)
                ->assertJsonPath('data.range', ['start_date' => $start, 'end_date' => $end]);
            $overview = $this->getJson(route('team.reports.data', ['start_date' => $start, 'end_date' => $end]))->assertOk();
            $this->assertSame($expected, $overview->json('data.groups.0.scheduled_minutes'));
        }
        $this->getJson(route('team.reports.groups.people', ['group' => $group, 'start_date' => '', 'end_date' => '']))
            ->assertOk()->assertJsonPath('data.totals.scheduled_minutes', 330);
    }

    public function test_group_people_rejects_foreign_and_missing_groups_and_ignores_scope_query_parameters(): void
    {
        $foreign = $this->event('Other festival');
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $other = $foreign->groups()->create(['name' => 'Foreign']);
        $local = $this->assign($this->shift($this->event), $group);
        $this->assign($this->shift($foreign), $other);
        $this->getJson(route('team.reports.groups.people', $other))->assertNotFound();
        $this->getJson(route('team.reports.groups.people', max($group->id, $other->id) + 100))->assertNotFound();
        $this->getJson(route('team.reports.groups.people', ['group' => $group, 'event_id' => $foreign->id, 'group_id' => $other->id]))
            ->assertOk()->assertJsonCount(1, 'data.people')
            ->assertJsonPath('data.people.0.team_engagement_id', $local->team_engagement_id)
            ->assertJsonPath('data.event_id', $this->event->id);
        $this->user->setCurrentEvent($foreign);
        $this->getJson(route('team.reports.groups.people', $group))->assertNotFound();
        $this->getJson(route('team.reports.groups.people', $other))->assertOk()->assertJsonPath('data.event_id', $foreign->id);
    }

    public function test_group_people_validates_dates_and_returns_an_empty_roster_successfully(): void
    {
        $group = $this->event->groups()->create(['name' => 'Empty']);
        $this->getJson(route('team.reports.groups.people', $group))->assertOk()
            ->assertJsonPath('data.people', [])->assertJsonPath('data.totals.scheduled_minutes', 0);
        foreach ([['start_date' => '2026-10-01'], ['end_date' => '2026-10-01'],
            ['start_date' => '2026-10-03', 'end_date' => '2026-10-01'],
            ['start_date' => '2026-02-30', 'end_date' => '2026-10-01']] as $params) {
            $this->getJson(route('team.reports.groups.people', ['group' => $group, ...$params]))->assertUnprocessable();
        }
        auth()->logout();
        $this->getJson(route('team.reports.groups.people', $group))->assertUnauthorized();
    }

    public function test_group_people_query_count_is_constant_and_uses_one_hours_aggregate(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $shift = $this->shift($this->event);
        $counts = [];
        foreach ([3, 300] as $size) {
            for ($i = $size === 3 ? 0 : 3; $i < $size; $i++) {
                $this->assign($shift, $group);
            }
            $this->actingAs($this->user->fresh());
            $this->app->forgetScopedInstances();
            Cache::flush();
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $this->getJson(route('team.reports.groups.people', $group))->assertOk()->assertJsonCount($size, 'data.people');
                $queries = DB::getQueryLog();
                $counts[] = count($queries);
                $aggregates = array_filter($queries, fn ($query) => str_contains($query['query'], 'scheduled_minutes'));
                $this->assertCount(1, $aggregates);
                $this->assertDoesNotMatchRegularExpression('/email|phone/i', array_values($aggregates)[0]['query']);
            } finally {
                DB::disableQueryLog();
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }

    public function test_report_address_restores_valid_event_scoped_group_and_date_filters(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $foreign = $this->event('Other festival')->groups()->create(['name' => 'Foreign']);
        $this->get(route('team.reports', ['group_id' => $group->id, 'start_date' => '2027-01-01', 'end_date' => '2027-01-03']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Team/Reports')
                ->where('filters', ['employment_type' => 'all', 'group_id' => $group->id, 'start_date' => '2027-01-01', 'end_date' => '2027-01-03'])
                ->where('groupOptions', [['group_id' => $group->id, 'name' => 'Gate']])
                ->missing('people')->missing('shifts'));
        foreach ([['group_id' => $foreign->id], ['group_id' => 'bad'], ['start_date' => '2027-01-01'],
            ['start_date' => '2027-01-03', 'end_date' => '2027-01-01']] as $params) {
            $this->getJson(route('team.reports', $params))->assertUnprocessable();
        }
    }

    public function test_shift_day_and_people_reconcile_personal_breaks_without_groups_or_money(): void
    {
        $group = $this->event->groups()->create(['name' => 'Secret group']);
        $night = $this->shift($this->event, '2026-09-30 22:00:00', '2026-10-01 06:00:00');
        $night->update(['name' => 'Night bar']);
        $supervisor = $this->assign($night, $group, breaks: [15, 30]);
        $supervisor->update(['is_supervisor' => true]);
        $supervisor->teamEngagement->person->update(['name' => 'Morgan']);
        $supervisor->teamEngagement->update(['status' => 'declined']);
        $late = $this->assign($night, $group, '2026-10-01 02:00:00', '2026-10-01 06:00:00', [30]);
        $zero = $this->assign($night, $group, '2026-10-01 02:00:00', '2026-10-01 02:30:00', [60]);
        $zero->teamEngagement->update(['employment_type' => 'volunteer', 'hourly_pay' => null]);
        $empty = $this->shift($this->event, '2026-09-30 08:00:00', '2026-09-30 10:00:00');
        $next = $this->shift($this->event);
        $this->assign($next, $group);
        $foreign = $this->event('Foreign');
        $otherGroup = $foreign->groups()->create(['name' => 'Other']);
        $otherShift = $this->shift($foreign, '2026-09-30 08:00:00', '2026-09-30 10:00:00');
        $this->assign($otherShift, $otherGroup);
        // Even malformed cross-event assignments cannot leak into counts or totals.
        $this->assign($night, $otherGroup);
        $expected = ShiftAssignmentHours::scheduledMinutes($supervisor, 45) + ShiftAssignmentHours::scheduledMinutes($late, 30);
        $response = $this->getJson(route('team.reports.shifts', ['date' => '2026-09-30', 'event_id' => $foreign->id]))->assertOk();
        $rows = collect($response->json('data.shifts'))->keyBy('shift_id');
        $this->assertSame([$empty->id, $night->id], $rows->keys()->all());
        $this->assertSame(0, $rows[$empty->id]['scheduled_minutes']);
        $this->assertSame(0, $rows[$empty->id]['people_count']);
        $this->assertNull($rows[$empty->id]['supervisor_name']);
        $this->assertSame(3, $rows[$night->id]['people_count']);
        $this->assertSame('Morgan', $rows[$night->id]['supervisor_name']);
        $this->assertSame($expected, $rows[$night->id]['scheduled_minutes']);
        $this->assertSame('2026-09-30T22:00', $rows[$night->id]['starts_at']);
        $this->assertSame('2026-10-01T06:00', $rows[$night->id]['ends_at']);
        $peopleResponse = $this->getJson(route('team.reports.shifts.people', $night))->assertOk();
        $people = $peopleResponse->json('data.people');
        $this->assertCount(3, $people);
        $this->assertSame($expected, array_sum(array_column($people, 'scheduled_minutes')));
        $this->assertSame($supervisor->team_engagement_id, $people[0]['team_engagement_id']);
        $this->assertTrue($people[0]['is_supervisor']);
        $this->assertSame(route('team.members.show', $supervisor->team_engagement_id), $people[0]['member_url']);
        $this->assertContains('volunteer', array_column($people, 'employment_type'));
        $this->assertContains(0, array_column($people, 'scheduled_minutes'));
        $this->assertSame(['event_id', 'employment_type', 'shift_id', 'people'], array_keys($peopleResponse->json('data')));
        foreach ([$response, $peopleResponse] as $result) {
            foreach (['email', 'phone', 'groups', 'group_id', 'group_name', 'totals'] as $field) {
                $this->assertStringNotContainsString('"'.$field.'"', $result->getContent());
            }
            $this->assertStringNotContainsString('Secret group', $result->getContent());
        }
        foreach ($people as $person) {
            $this->assertNull($person['approved_minutes']);
            $this->assertNull($person['unapproved_minutes']);
        }
        $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01']))->assertOk()
            ->assertJsonCount(1, 'data.shifts')->assertJsonPath('data.shifts.0.shift_id', $next->id);
        $this->getJson(route('team.reports.shifts.people', $empty))->assertOk()->assertJsonPath('data.people', []);
    }

    public function test_shift_report_validates_dates_and_rejects_foreign_or_missing_shifts(): void
    {
        foreach ([[], ['date' => '2026-02-30'], ['date' => 'bad'], ['date' => '2026-1-01']] as $params) {
            $this->getJson(route('team.reports.shifts', $params))->assertUnprocessable()->assertJsonValidationErrors('date');
        }
        $this->getJson(route('team.reports.shifts', ['date' => '2027-01-01']))->assertOk()->assertJsonPath('data.shifts', []);
        $foreign = $this->shift($this->event('Foreign'));
        $this->getJson(route('team.reports.shifts.people', $foreign))->assertNotFound();
        $this->getJson(route('team.reports.shifts.people', $foreign->id + 100))->assertNotFound();
        auth()->logout();
        $this->getJson(route('team.reports.shifts', ['date' => '2026-10-01']))->assertUnauthorized();
        $this->getJson(route('team.reports.shifts.people', $foreign))->assertUnauthorized();
    }

    public function test_shift_report_address_uses_event_local_today_without_clamping_and_restores_explicit_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01 03:00:00', 'UTC'));
        $this->get(route('team.reports'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shiftFilters', ['view' => 'summary', 'date' => '2026-12-31']));
        $this->get(route('team.reports', ['view' => 'shift', 'date' => '2027-03-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('shiftFilters', ['view' => 'shift', 'date' => '2027-03-01'])->missing('shifts')->missing('people'));
        foreach ([['view' => 'other'], ['date' => '2026-02-30']] as $params) {
            $this->getJson(route('team.reports', $params))->assertUnprocessable();
        }
    }

    public function test_report_tabs_restore_explicit_and_legacy_addresses(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        foreach ([['view' => 'summary'], ['view' => 'team'], ['group_id' => $group->id], ['view' => 'summary', 'group_id' => $group->id]] as $params) {
            $expected = $params['view'] ?? 'team';
            $this->get(route('team.reports', $params))->assertInertia(fn (AssertableInertia $page) => $page
                ->where('shiftFilters.view', $expected));
        }
    }

    public function test_shift_report_queries_are_constant_for_three_and_three_hundred_people(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $shift = $this->shift($this->event);
        $counts = [];
        foreach ([3, 300] as $size) {
            for ($i = ($size === 3 ? 0 : 3); $i < $size; $i++) {
                $this->assign($shift, $group);
            }
            foreach (['day' => route('team.reports.shifts', ['date' => '2026-10-01']), 'people' => route('team.reports.shifts.people', $shift)] as $kind => $url) {
                $this->actingAs($this->user->fresh());
                $this->app->forgetScopedInstances();
                Cache::flush();
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $response = $this->getJson($url)->assertOk();
                    $this->assertCount($kind === 'day' ? 1 : $size, $response->json('data.'.($kind === 'day' ? 'shifts' : 'people')));
                    $queries = DB::getQueryLog();
                    $counts[$kind][] = count($queries);
                    $hours = array_values(array_filter($queries, fn ($query) => str_contains($query['query'], 'scheduled_minutes')));
                    $this->assertCount(1, $hours);
                    $this->assertDoesNotMatchRegularExpression('/email|phone/i', $hours[0]['query']);
                } finally {
                    DB::disableQueryLog();
                }
            }
        }
        $this->assertSame($counts['day'][0], $counts['day'][1]);
        $this->assertSame($counts['people'][0], $counts['people'][1]);
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
    }

    private function shift(Event $event, string $start = '2026-10-01 08:00:00', string $end = '2026-10-01 12:00:00'): Shift
    {
        return $event->shifts()->create(['location_id' => $event->locations()->firstOrCreate(['name' => 'Gate'])->id,
            'starts_at' => $start, 'ends_at' => $end]);
    }

    private function assign(Shift $shift, Group $group, ?string $start = null, ?string $end = null, array $breaks = []): ShiftAssignment
    {
        $member = $this->createTeamEngagement(['event_id' => $group->event_id, 'group_id' => $group->id,
            'person_id' => Person::create(['name' => 'Private member', 'email' => fake()->unique()->safeEmail()])->id,
            'status' => 'hired', 'employment_type' => 'paid', 'hourly_pay' => 50]);
        $assignment = $shift->assignments()->create(['team_engagement_id' => $member->id,
            'starts_at' => $start ?? $shift->starts_at, 'ends_at' => $end ?? $shift->ends_at]);
        foreach ($breaks as $i => $minutes) {
            $assignment->breaks()->create(['duration_minutes' => $minutes, 'starts_at' => $assignment->starts_at, 'sort_order' => $i]);
        }

        return $assignment;
    }
}
