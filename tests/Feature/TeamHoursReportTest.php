<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\ShiftAssignmentHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function test_initial_overview_reconciles_all_groups_including_zero_rows_without_detail_or_pay_data(): void
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
        $this->assertSame(['event_id', 'range', 'totals', 'groups'], array_keys($data));
        $this->assertSame($this->event->id, $data['event_id']);
        $this->assertSame(['start_date' => null, 'end_date' => null], $data['range']);
        $this->assertSame(['scheduled_minutes' => $expected, 'approved_minutes' => null, 'unapproved_minutes' => null], $data['totals']);
        $this->assertCount(28, $data['groups']);
        $this->assertSame(['Gate', 'Stage', ...array_map(fn ($i) => sprintf('Unused %02d', $i), range(0, 25))], array_column($data['groups'], 'name'));
        $this->assertSame($expected, array_sum(array_column($data['groups'], 'scheduled_minutes')));
        foreach ($data['groups'] as $row) {
            $this->assertSame(['group_id', 'name', 'scheduled_minutes', 'approved_minutes', 'unapproved_minutes'], array_keys($row));
            $this->assertNull($row['approved_minutes']);
            $this->assertNull($row['unapproved_minutes']);
        }
        $this->assertSame(0, $data['groups'][27]['scheduled_minutes']);
        foreach (['people', 'days', 'shifts', 'assignments', 'hourly_pay', 'employment_type'] as $key) {
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
            'range' => ['start_date' => null, 'end_date' => null],
            'totals' => ['scheduled_minutes' => 0, 'approved_minutes' => null, 'unapproved_minutes' => null],
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
                $this->assertDoesNotMatchRegularExpression('/hourly_pay|employment_type|"people"|"users"/i', $sql);
            } finally {
                DB::disableQueryLog();
            }
        }
        $this->assertSame($counts[0], $counts[1]);
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
