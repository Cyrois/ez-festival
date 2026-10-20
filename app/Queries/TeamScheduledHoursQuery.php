<?php

namespace App\Queries;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamScheduledHoursQuery
{
    /** Composable SQL rows: callers aggregate before fetching, never hydrate all assignments. */
    public function forEvent(Event $event, ?string $startDate = null, ?string $endDate = null): Builder
    {
        $breaks = DB::table('shift_assignment_breaks as b')
            ->join('shift_assignments as ba', 'ba.id', '=', 'b.shift_assignment_id')
            ->join('shifts as bs', 'bs.id', '=', 'ba.shift_id')
            ->join('team_engagements as bt', 'bt.id', '=', 'ba.team_engagement_id')
            ->where('bs.event_id', $event->id)->where('bt.event_id', $event->id)
            ->select('b.shift_assignment_id')->selectRaw('SUM(b.duration_minutes) as break_minutes')
            ->groupBy('b.shift_assignment_id');

        // Times are naive event-local wall clocks, just like ShiftAssignmentHours.
        [$duration, $clamp] = match (DB::connection()->getDriverName()) {
            'pgsql' => ['CAST(TRUNC(EXTRACT(EPOCH FROM (a.ends_at - a.starts_at)) / 60) AS BIGINT)', 'GREATEST'],
            'sqlite' => ["CAST((strftime('%s', a.ends_at) - strftime('%s', a.starts_at)) / 60 AS INTEGER)", 'MAX'],
            default => throw new \LogicException('Scheduled hours require PostgreSQL or SQLite.'),
        };

        $assignments = DB::table('shift_assignments as a')
            ->join('shifts as s', 's.id', '=', 'a.shift_id')
            ->join('team_engagements as t', 't.id', '=', 'a.team_engagement_id')
            ->join('groups as g', 'g.id', '=', 't.group_id')
            ->where('s.event_id', $event->id)->where('t.event_id', $event->id)->where('g.event_id', $event->id)
            ->when($startDate !== null, fn (Builder $query) => $query->whereDate('s.starts_at', '>=', $startDate))
            ->when($endDate !== null, fn (Builder $query) => $query->whereDate('s.starts_at', '<=', $endDate))
            ->leftJoinSub($breaks, 'breaks', 'breaks.shift_assignment_id', '=', 'a.id')
            ->select('a.id as assignment_id', 'a.shift_id', 'a.team_engagement_id', 't.person_id', 't.group_id', 's.starts_at as shift_starts_at')
            ->selectRaw("DATE(s.starts_at) as day, {$clamp}(0, {$duration} - COALESCE(breaks.break_minutes, 0)) as scheduled_minutes");

        return DB::query()->fromSub($assignments, 'scheduled');
    }

    public function eventTotal(Event $event): int
    {
        return (int) $this->forEvent($event)->sum('scheduled_minutes');
    }

    public function groups(Event $event, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $totals = $this->forEvent($event, $startDate, $endDate)->select('group_id')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes')->groupBy('group_id');

        return DB::table('groups')->where('groups.event_id', $event->id)
            ->leftJoinSub($totals, 'totals', 'totals.group_id', '=', 'groups.id')
            ->select('groups.id as group_id', 'groups.name as group_name')->selectRaw('COALESCE(totals.scheduled_minutes, 0) as scheduled_minutes')
            ->orderBy('groups.name')->orderBy('groups.id')->get()->map(fn ($row): array => [
                'group_id' => (int) $row->group_id, 'group_name' => $row->group_name, 'scheduled_minutes' => (int) $row->scheduled_minutes,
            ]);
    }

    public function groupDays(Event $event, int $groupId): Collection
    {
        return $this->forEvent($event)->where('group_id', $groupId)
            ->select('day')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes')->groupBy('day')->orderBy('day')
            ->get()->map(fn ($row): array => ['day' => $row->day, 'scheduled_minutes' => (int) $row->scheduled_minutes]);
    }

    /** Aggregate IDs only; people-list consumers can paginate/join names on the server. */
    public function groupPeople(Event $event, int $groupId): Builder
    {
        return $this->forEvent($event)->where('group_id', $groupId)
            ->select('team_engagement_id', 'person_id')->selectRaw('CAST(SUM(scheduled_minutes) AS BIGINT) as scheduled_minutes')
            ->groupBy('team_engagement_id', 'person_id')->orderBy('team_engagement_id');
    }

    public function personShifts(Event $event, int $engagementId): Collection
    {
        return $this->forEvent($event)->where('team_engagement_id', $engagementId)
            ->select('shift_id', 'day', 'shift_starts_at')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes')
            ->groupBy('shift_id', 'day', 'shift_starts_at')->orderBy('shift_starts_at')->orderBy('shift_id')
            ->get()->map(fn ($row): array => [
                'shift_id' => (int) $row->shift_id, 'day' => $row->day, 'scheduled_minutes' => (int) $row->scheduled_minutes,
            ]);
    }

    public function dayShifts(Event $event, string $day): Collection
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, 'UTC');
        $totals = $this->forEvent($event)->where('shift_starts_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('shift_starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'))
            ->select('shift_id')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes')->groupBy('shift_id');

        return DB::table('shifts')->where('shifts.event_id', $event->id)
            ->where('shifts.starts_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('shifts.starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'))
            ->leftJoinSub($totals, 'totals', 'totals.shift_id', '=', 'shifts.id')
            ->select('shifts.id as shift_id')->selectRaw('COALESCE(totals.scheduled_minutes, 0) as scheduled_minutes')
            ->orderBy('shifts.starts_at')->orderBy('shifts.id')->get()->map(fn ($row): array => [
                'shift_id' => (int) $row->shift_id, 'scheduled_minutes' => (int) $row->scheduled_minutes,
            ]);
    }
}
