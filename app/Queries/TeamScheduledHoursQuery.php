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
            ->select('a.id as assignment_id', 'a.shift_id', 'a.team_engagement_id', 'a.is_supervisor', 't.person_id', 't.group_id', 's.starts_at as shift_starts_at')
            ->selectRaw("DATE(s.starts_at) as day, {$clamp}(0, {$duration} - COALESCE(breaks.break_minutes, 0)) as scheduled_minutes");

        return DB::query()->fromSub($assignments, 'scheduled');
    }

    public function eventTotal(Event $event): int
    {
        return (int) $this->forEvent($event)->sum('scheduled_minutes');
    }

    public function groups(Event $event, ?string $startDate = null, ?string $endDate = null, string $employmentType = 'all'): Collection
    {
        // Round each person's range cost before summing their team, including eligible zero-hour members.
        $totals = DB::query()->fromSub($this->reportRosterQuery($event, $startDate, $endDate, $employmentType), 'roster')
            ->select('group_id')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes, SUM(scheduled_cost_cents) as scheduled_cost_cents, SUM(missing_rate_count) as missing_rate_count')
            ->groupBy('group_id');

        return DB::table('groups')->where('groups.event_id', $event->id)
            ->leftJoinSub($totals, 'totals', 'totals.group_id', '=', 'groups.id')
            ->when($employmentType !== 'all', fn (Builder $query) => $query->whereNotNull('totals.group_id'))
            ->select('groups.id as group_id', 'groups.name as group_name')
            ->selectRaw('COALESCE(totals.scheduled_minutes, 0) as scheduled_minutes, COALESCE(totals.scheduled_cost_cents, 0) as scheduled_cost_cents, COALESCE(totals.missing_rate_count, 0) as missing_rate_count')
            ->orderBy('groups.name')->orderBy('groups.id')->get()->map(fn ($row): array => [
                'group_id' => (int) $row->group_id, 'group_name' => $row->group_name,
                'scheduled_minutes' => (int) $row->scheduled_minutes,
                'scheduled_cost_cents' => (int) $row->scheduled_cost_cents,
                'missing_rate_count' => (int) $row->missing_rate_count,
            ]);
    }

    /** Whole-event figures for Budget, independent of report selections. */
    public function eventCostTotals(Event $event): array
    {
        $total = DB::query()->fromSub($this->reportRosterQuery($event), 'roster')
            ->selectRaw('COALESCE(SUM(scheduled_cost_cents), 0) as scheduled_cost_cents, COALESCE(SUM(missing_rate_count), 0) as missing_rate_count')->first();

        return ['scheduled_cost_cents' => (int) $total->scheduled_cost_cents, 'approved_cost_cents' => null, 'missing_rate_count' => (int) $total->missing_rate_count];
    }

    public function groupDays(Event $event, int $groupId): Collection
    {
        return $this->forEvent($event)->where('group_id', $groupId)
            ->select('day')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes')->groupBy('day')->orderBy('day')
            ->get()->map(fn ($row): array => ['day' => $row->day, 'scheduled_minutes' => (int) $row->scheduled_minutes]);
    }

    /** Aggregate IDs only; people-list consumers can paginate/join names on the server. */
    public function groupPeople(Event $event, int $groupId, ?string $startDate = null, ?string $endDate = null): Builder
    {
        return $this->forEvent($event, $startDate, $endDate)->where('group_id', $groupId)
            ->select('team_engagement_id', 'person_id')->selectRaw('CAST(SUM(scheduled_minutes) AS BIGINT) as scheduled_minutes')
            ->groupBy('team_engagement_id', 'person_id')->orderBy('team_engagement_id');
    }

    /** Hired members at zero hours plus anyone with assignments in the range, regardless of status. */
    public function groupRoster(Event $event, int $groupId, ?string $startDate = null, ?string $endDate = null, string $employmentType = 'all'): Collection
    {
        return $this->reportRosterQuery($event, $startDate, $endDate, $employmentType, $groupId)
            ->join('people', 'people.id', '=', 'members.person_id')->addSelect('people.name')
            ->orderBy('people.name')->orderBy('members.id')->get();
    }

    private function reportRosterQuery(Event $event, ?string $startDate = null, ?string $endDate = null, string $employmentType = 'all', ?int $groupId = null): Builder
    {
        $hours = $this->forEvent($event, $startDate, $endDate)
            ->when($groupId !== null, fn (Builder $query) => $query->where('group_id', $groupId))
            ->select('team_engagement_id')->selectRaw('CAST(SUM(scheduled_minutes) AS BIGINT) as scheduled_minutes')->groupBy('team_engagement_id');

        return DB::table('team_engagements as members')
            ->join('groups', 'groups.id', '=', 'members.group_id')
            ->where('members.event_id', $event->id)->where('groups.event_id', $event->id)
            ->when($groupId !== null, fn (Builder $query) => $query->where('members.group_id', $groupId))
            ->when($employmentType !== 'all', fn (Builder $query) => $query->where('members.employment_type', $employmentType))
            ->leftJoinSub($hours, 'hours', 'hours.team_engagement_id', '=', 'members.id')
            ->where(fn (Builder $query) => $query->where('members.status', 'hired')->orWhereNotNull('hours.team_engagement_id'))
            ->select('members.id as team_engagement_id', 'members.person_id', 'members.group_id', 'members.employment_type')
            ->selectRaw("CASE WHEN members.employment_type = 'paid' THEN members.hourly_pay END as hourly_pay")
            ->selectRaw('COALESCE(hours.scheduled_minutes, 0) as scheduled_minutes')
            ->selectRaw($this->costExpression('COALESCE(hours.scheduled_minutes, 0)').' as scheduled_cost_cents')
            ->selectRaw("CASE WHEN members.employment_type = 'paid' AND members.hourly_pay IS NULL THEN 1 ELSE 0 END as missing_rate_count");
    }

    /** Integer cents: half-up rounding after minutes have been grouped for this person row. */
    private function costExpression(string $minutes): string
    {
        $rateCents = 'CAST(ROUND(COALESCE(members.hourly_pay, 0) * 100, 0) AS BIGINT)';

        return "CASE WHEN members.employment_type = 'paid' THEN CAST(({$minutes} * {$rateCents} + 30) / 60 AS BIGINT) ELSE 0 END";
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
        return $this->dayShiftQuery($event, $day)
            ->select('shifts.id as shift_id')->selectRaw('COALESCE(totals.scheduled_minutes, 0) as scheduled_minutes')
            ->get()->map(fn ($row): array => [
                'shift_id' => (int) $row->shift_id, 'scheduled_minutes' => (int) $row->scheduled_minutes,
            ]);
    }

    public function dayShiftReport(Event $event, string $day, string $employmentType = 'all'): Collection
    {
        return $this->dayShiftQuery($event, $day, $employmentType)
            ->join('locations', fn ($join) => $join->on('locations.id', '=', 'shifts.location_id')->where('locations.event_id', $event->id))
            ->leftJoin('people as supervisor', 'supervisor.id', '=', 'totals.supervisor_person_id')
            ->select('shifts.id as shift_id', 'shifts.name', 'shifts.starts_at', 'shifts.ends_at', 'locations.name as location', 'supervisor.name as supervisor_name')
            ->selectRaw('COALESCE(totals.scheduled_minutes, 0) as scheduled_minutes, COALESCE(totals.people_count, 0) as people_count, COALESCE(totals.scheduled_cost_cents, 0) as scheduled_cost_cents, COALESCE(totals.missing_rate_count, 0) as missing_rate_count')
            ->get();
    }

    public function shiftPeople(Event $event, int $shiftId, string $employmentType = 'all'): Collection
    {
        return $this->forEvent($event)->where('scheduled.shift_id', $shiftId)
            ->join('people', 'people.id', '=', 'scheduled.person_id')
            ->join('team_engagements as members', 'members.id', '=', 'scheduled.team_engagement_id')
            ->when($employmentType !== 'all', fn (Builder $query) => $query->where('members.employment_type', $employmentType))
            ->select('scheduled.team_engagement_id', 'scheduled.person_id', 'scheduled.is_supervisor', 'scheduled.scheduled_minutes', 'people.name', 'members.employment_type')
            ->selectRaw("CASE WHEN members.employment_type = 'paid' THEN members.hourly_pay END as hourly_pay")
            ->selectRaw($this->costExpression('scheduled.scheduled_minutes').' as scheduled_cost_cents')
            ->selectRaw("CASE WHEN members.employment_type = 'paid' AND members.hourly_pay IS NULL THEN 1 ELSE 0 END as missing_rate_count")
            ->orderByDesc('scheduled.is_supervisor')->orderBy('people.name')->orderBy('scheduled.team_engagement_id')->get();
    }

    private function dayShiftQuery(Event $event, string $day, string $employmentType = 'all'): Builder
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, 'UTC');
        $totals = $this->forEvent($event)
            ->join('team_engagements as members', 'members.id', '=', 'scheduled.team_engagement_id')
            ->when($employmentType !== 'all', fn (Builder $query) => $query->where('members.employment_type', $employmentType))
            ->where('shift_starts_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('shift_starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'))
            ->select('shift_id')->selectRaw('SUM(scheduled_minutes) as scheduled_minutes, COUNT(*) as people_count, MAX(CASE WHEN is_supervisor THEN scheduled.person_id END) as supervisor_person_id')
            ->selectRaw('SUM('.$this->costExpression('scheduled.scheduled_minutes').') as scheduled_cost_cents')
            ->selectRaw("SUM(CASE WHEN members.employment_type = 'paid' AND members.hourly_pay IS NULL THEN 1 ELSE 0 END) as missing_rate_count")->groupBy('shift_id');

        return DB::table('shifts')->where('shifts.event_id', $event->id)
            ->where('shifts.starts_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('shifts.starts_at', '<', $start->addDay()->format('Y-m-d H:i:s'))
            ->leftJoinSub($totals, 'totals', 'totals.shift_id', '=', 'shifts.id')
            ->when($employmentType !== 'all', fn (Builder $query) => $query->whereNotNull('totals.shift_id'))
            ->orderBy('shifts.starts_at')->orderBy('shifts.id');
    }
}
