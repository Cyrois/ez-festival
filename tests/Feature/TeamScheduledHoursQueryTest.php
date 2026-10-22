<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\ShiftAssignmentHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamScheduledHoursQueryTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private TeamScheduledHoursQuery $hours;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->event('Festival');
        $this->hours = app(TeamScheduledHoursQuery::class);
    }

    public function test_all_projections_match_php_and_reconcile_without_break_join_fanout(): void
    {
        $gate = $this->event->groups()->create(['name' => 'Gate']);
        $stage = $this->event->groups()->create(['name' => 'Stage']);
        $empty = $this->event->groups()->create(['name' => 'Empty']);
        $night = $this->shift($this->event, '2026-10-01 22:00:00', '2026-10-02 06:00:00');
        $morning = $this->shift($this->event, '2026-10-02 08:00:00', '2026-10-02 12:00:00');
        $vacant = $this->shift($this->event, '2026-10-01 12:00:00', '2026-10-01 14:00:00');
        $first = $this->member($gate);
        $second = $this->member($gate);
        $third = $this->member($stage);
        $rows = collect([
            $this->assign($night, $first, '2026-10-01 22:00:00', '2026-10-02 06:00:00', [15, 30]),
            $this->assign($night, $second, '2026-10-02 02:00:00', '2026-10-02 06:00:00', [30]),
            $this->assign($night, $third, '2026-10-02 02:00:00', '2026-10-02 03:00:00', [60]),
            $this->assign($morning, $first, '2026-10-02 08:00:59', '2026-10-02 10:00:00'),
            $this->assign($morning, $third, '2026-10-02 08:00:00', '2026-10-02 08:30:00', [60]),
        ])->each->load(['breaks', 'teamEngagement', 'shift']);
        // Shift defaults must never replace personal break snapshots.
        $night->breaks()->create(['duration_minutes' => 60, 'starts_at' => '2026-10-02 03:00:00', 'sort_order' => 0]);
        $this->createTeamEngagement(['event_id' => $this->event->id,
            'person_id' => Person::create(['name' => 'Ungrouped applicant', 'email' => 'applicant@example.test'])->id, 'status' => 'applied']);
        $minutes = fn ($assignment) => ShiftAssignmentHours::scheduledMinutes($assignment, $assignment->breaks->sum('duration_minutes'));
        $this->assertSame(764, $rows->sum($minutes));
        $this->assertSame($rows->sum($minutes), $this->hours->eventTotal($this->event));
        $groups = $this->hours->groups($this->event)->keyBy('group_id');
        $this->assertCount(3, $groups);
        $this->assertSame(0, $groups[$empty->id]['scheduled_minutes']);
        $this->assertSame(764, $groups->sum('scheduled_minutes'));
        foreach ([$gate, $stage] as $group) {
            $assignments = $rows->filter(fn ($row) => $row->teamEngagement->group_id === $group->id);
            $expected = $assignments->sum($minutes);
            $this->assertSame($expected, $groups[$group->id]['scheduled_minutes']);
            $days = $this->hours->groupDays($this->event, $group->id);
            $people = $this->hours->groupPeople($this->event, $group->id)->get();
            $this->assertSame($expected, $days->sum('scheduled_minutes'));
            $this->assertEquals($expected, $people->sum('scheduled_minutes'));
            foreach ($days as $day) {
                $this->assertSame($assignments->filter(fn ($row) => $row->shift->starts_at->toDateString() === $day['day'])->sum($minutes), $day['scheduled_minutes']);
            }
            foreach ($people as $person) {
                $this->assertEquals($assignments->where('team_engagement_id', $person->team_engagement_id)->sum($minutes), $person->scheduled_minutes);
                $shifts = $this->hours->personShifts($this->event, $person->team_engagement_id);
                $this->assertEquals($person->scheduled_minutes, $shifts->sum('scheduled_minutes'));
                foreach ($shifts as $shift) {
                    $this->assertSame($assignments->where('team_engagement_id', $person->team_engagement_id)->where('shift_id', $shift['shift_id'])->sum($minutes), $shift['scheduled_minutes']);
                }
            }
        }
        $this->assertSame([['day' => '2026-10-01', 'scheduled_minutes' => 645], ['day' => '2026-10-02', 'scheduled_minutes' => 119]], $this->hours->groupDays($this->event, $gate->id)->all());
        foreach (['2026-10-01', '2026-10-02'] as $day) {
            foreach ($this->hours->dayShifts($this->event, $day) as $shift) {
                $this->assertSame($rows->where('shift_id', $shift['shift_id'])->sum($minutes), $shift['scheduled_minutes']);
            }
        }
        $this->assertSame(0, $this->hours->dayShifts($this->event, '2026-10-01')->keyBy('shift_id')[$vacant->id]['scheduled_minutes']);
        $first->update(['status' => 'declined']);
        $this->assertSame(764, $this->hours->eventTotal($this->event));
        $this->event->lock();
        $this->assertSame(764, $this->hours->eventTotal($this->event));
    }

    public function test_scope_checks_both_sides_of_assignments_and_foreign_children_are_empty(): void
    {
        $foreign = $this->event('Other festival');
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $otherGroup = $foreign->groups()->create(['name' => 'Gate']);
        $member = $this->member($group);
        $otherMember = $this->member($otherGroup);
        $shift = $this->shift($this->event);
        $otherShift = $this->shift($foreign);
        $this->assign($shift, $member);
        $this->assign($otherShift, $otherMember);
        $this->assign($otherShift, $member);
        $this->assign($shift, $otherMember);
        $this->assertSame(240, $this->hours->eventTotal($this->event));
        $this->assertSame(240, $this->hours->eventTotal($foreign));
        $this->assertSame([], $this->hours->groupDays($this->event, $otherGroup->id)->all());
        $this->assertSame([], $this->hours->groupPeople($this->event, $otherGroup->id)->get()->all());
        $this->assertSame([], $this->hours->personShifts($this->event, $otherMember->id)->all());
        $this->assertSame([['shift_id' => $shift->id, 'scheduled_minutes' => 240]], $this->hours->dayShifts($this->event, '2026-10-01')->all());
        $missing = (new Event)->forceFill(['id' => max($this->event->id, $foreign->id) + 100]);
        $this->assertSame(0, $this->hours->eventTotal($missing));
        $this->assertSame([], $this->hours->groups($missing)->all());
        $this->assertSame([], $this->hours->dayShifts($missing, '2026-10-01')->all());
    }

    public function test_empty_event_and_wall_clock_duration_across_dst(): void
    {
        $this->assertSame(0, $this->hours->eventTotal($this->event));
        $this->assertSame([], $this->hours->groups($this->event)->all());
        $this->assertSame([], $this->hours->dayShifts($this->event, '2026-10-01')->all());
        $group = $this->event->groups()->create(['name' => 'Gate']);
        foreach (['2026-03-08', '2026-11-01'] as $day) {
            $shift = $this->shift($this->event, "$day 00:00:00", "$day 04:00:00");
            $assignment = $this->assign($shift, $this->member($group), "$day 00:00:00", "$day 04:00:00", [30]);
            $this->assertSame(ShiftAssignmentHours::scheduledMinutes($assignment, 30), $this->hours->dayShifts($this->event, $day)->sole()['scheduled_minutes']);
        }
        $this->assertSame(420, $this->hours->eventTotal($this->event));
    }

    public function test_each_projection_is_one_query_for_three_and_three_hundred_people_and_reads_no_contact_details(): void
    {
        $group = $this->event->groups()->create(['name' => 'Gate']);
        $shift = $this->shift($this->event);
        $member = $this->member($group);
        $this->assign($shift, $member);
        $this->assign($shift, $this->member($group));
        $this->assign($shift, $this->member($group));
        foreach ([3, 300] as $size) {
            if ($size === 300) {
                for ($i = 3; $i < 300; $i++) {
                    $this->assign($shift, $this->member($group));
                }
            }
            foreach ([fn () => $this->hours->eventTotal($this->event), fn () => $this->hours->groups($this->event), fn () => $this->hours->eventCostTotals($this->event),
                fn () => $this->hours->groupDays($this->event, $group->id), fn () => $this->hours->groupPeople($this->event, $group->id)->get(),
                fn () => $this->hours->personShifts($this->event, $member->id), fn () => $this->hours->dayShifts($this->event, '2026-10-01')] as $projection) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $projection();
                    $queries = DB::getQueryLog();
                    $this->assertCount(1, $queries);
                    $this->assertDoesNotMatchRegularExpression('/"people"|"users"/i', $queries[0]['query']);
                } finally {
                    DB::disableQueryLog();
                }
            }
            $this->assertSame($size * 240, $this->hours->eventTotal($this->event));
        }
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
    }

    private function member(Group $group): TeamEngagement
    {
        return $this->createTeamEngagement(['event_id' => $group->event_id, 'group_id' => $group->id,
            'person_id' => Person::create(['name' => 'Member', 'email' => fake()->unique()->safeEmail()])->id, 'status' => 'hired']);
    }

    private function shift(Event $event, string $start = '2026-10-01 08:00:00', string $end = '2026-10-01 12:00:00'): Shift
    {
        $location = $event->locations()->firstOrCreate(['name' => 'Gate']);

        return $event->shifts()->create(['location_id' => $location->id, 'starts_at' => $start, 'ends_at' => $end]);
    }

    private function assign(Shift $shift, TeamEngagement $member, ?string $start = null, ?string $end = null, array $breaks = []): ShiftAssignment
    {
        $assignment = $shift->assignments()->create(['team_engagement_id' => $member->id, 'starts_at' => $start ?? $shift->starts_at, 'ends_at' => $end ?? $shift->ends_at]);
        foreach ($breaks as $i => $minutes) {
            $assignment->breaks()->create(['duration_minutes' => $minutes, 'starts_at' => $assignment->starts_at, 'sort_order' => $i]);
        }

        return $assignment;
    }
}
