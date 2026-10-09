<?php

namespace App\Queries;

use App\Models\Event;
use App\Support\MealClaimDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealEntitlementQuery
{
    /** Person-owned grants, with claim state stored on the same row. */
    public function forPerson(Event $event, int $memberId, ?CarbonInterface $searchDate = null): Builder
    {
        $query = $this->forPersonAcrossEvent($event, $memberId);

        if ($searchDate === null) {
            return MealClaimDay::scope($query, $event);
        }

        $day = $searchDate->toDateString();
        // Count distinct meals, retaining every grant for each selected meal.
        $previous = (clone $query)->select('meal_id')->where('meal_date', '<', $day)->groupBy('meal_id')
            ->orderByRaw('MAX(meal_date) DESC')->orderByRaw('MAX(starts_at) DESC')->orderByDesc('meal_id')->limit(5);
        $next = (clone $query)->select('meal_id')->where('meal_date', '>', $day)->groupBy('meal_id')
            ->orderByRaw('MIN(meal_date) ASC')->orderByRaw('MIN(starts_at) ASC')->orderBy('meal_id')->limit(4);

        return $query->where(fn (Builder $visible) => $visible->where('meal_date', $day)
            ->orWhere(fn (Builder $before) => $before->where('meal_date', '<', $day)->whereIn('meal_id', $previous))
            ->orWhere(fn (Builder $after) => $after->where('meal_date', '>', $day)->whereIn('meal_id', $next)));
    }

    /** The same visible assignments across every meal day, without Kitchen's today filter. */
    public function forPersonAcrossEvent(Event $event, int $memberId): Builder
    {
        $rows = DB::table('meal_assignments as grants')->join('meal_types', 'meal_types.id', '=', 'grants.meal_type_id')
            ->where('grants.event_id', $event->id)->where('grants.team_engagement_id', $memberId)
            ->where(fn (Builder $visible) => $visible->whereNotNull('grants.claimed_at')
                ->orWhere(fn (Builder $active) => $this->active($active)))
            ->select('grants.id as assignment_id', 'grants.shift_meal_id', 'grants.shift_assignment_id', 'grants.is_active', 'grants.source_shift_id', 'grants.meal_id',
                'grants.meal_name', DB::raw('DATE(grants.meal_date) as meal_date'), 'grants.meal_type_id', 'meal_types.name as type_name',
                'grants.starts_at', 'grants.ends_at', 'grants.shift_location_name', 'grants.shift_starts_at', 'grants.shift_ends_at',
                'grants.claimed_at', 'grants.claim_token')
            ->addSelect('grants.is_override', 'grants.override_given_at')
            ->selectRaw('CASE WHEN grants.claimed_at IS NULL THEN 0 ELSE 1 END as used');

        return DB::query()->fromSub($rows, 'person_meals');
    }

    /** Null source is a direct assignment; Scheduling removals retire only that source grant. */
    private function active(Builder $query): Builder
    {
        return $query->where('grants.is_active', true)->where(fn (Builder $source) => $source
            ->whereNull('grants.source_shift_id')->orWhere(fn (Builder $linked) => $linked
            ->whereNotNull('grants.shift_meal_id')->whereNotNull('grants.shift_assignment_id')));
    }

    public function personCounts(Event $event, int $memberId, ?CarbonInterface $searchDate = null): Collection
    {
        return $this->forPerson($event, $memberId, $searchDate)->select('meal_date', 'meal_type_id', 'type_name')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN used = 0 THEN 1 ELSE 0 END) as remaining, SUM(CASE WHEN is_override AND used = 1 THEN 1 ELSE 0 END) as overrides, SUM(CASE WHEN is_override THEN 0 ELSE 1 END) as normal_total')
            ->groupBy('meal_date', 'meal_type_id', 'type_name')->orderBy('meal_date')->orderBy('meal_type_id')->get();
    }

    /** One row per shift meal and listed person; deliberately never deduplicated by meal. */
    public function forEvent(Event $event): Builder
    {
        return $this->active(DB::table('meal_assignments as grants'))
            ->where('grants.is_override', false)
            ->leftJoin('shift_meals', 'shift_meals.id', '=', 'grants.shift_meal_id')
            ->leftJoin('shifts', 'shifts.id', '=', 'grants.source_shift_id')
            ->join('meals', 'meals.id', '=', 'grants.meal_id')
            ->leftJoin('shift_assignments', 'shift_assignments.id', '=', 'grants.shift_assignment_id')
            ->join('team_engagements', 'team_engagements.id', '=', 'grants.team_engagement_id')
            ->where('grants.event_id', $event->id)->where('meals.event_id', $event->id)
            ->where('team_engagements.event_id', $event->id)
            ->select('grants.shift_meal_id', 'grants.meal_id', 'grants.source_shift_id as shift_id',
                'grants.shift_assignment_id as assignment_id', 'grants.team_engagement_id', 'team_engagements.person_id');
    }

    /** Load all meal rows and their valid recipients in batches for the roster. */
    public function loadForShifts(EloquentCollection $shifts, Event $event): void
    {
        if ($shifts->isEmpty()) {
            return;
        }
        $shifts->load('meals.meal.mealType');
        $grants = $this->forEvent($event)->whereIn('shifts.id', $shifts->modelKeys())->get()->groupBy('shift_meal_id');
        foreach ($shifts as $shift) {
            foreach ($shift->meals as $row) {
                $row->setAttribute('assignment_ids', $grants->get($row->id, collect())->pluck('assignment_id')->map(fn ($id) => (int) $id)->all());
            }
        }
    }

    public function projected(Event $event): Collection
    {
        return $this->forEvent($event)->select('grants.meal_id')->selectRaw('COUNT(*) as quantity')
            ->groupBy('grants.meal_id')->pluck('quantity', 'meal_id');
    }

    /** All eligible-day meals, with availability based on the full grant set rather than a table page. */
    public function overrideOptions(Event $event, int $memberId): Collection
    {
        $counts = $this->personCounts($event, $memberId)->keyBy(fn ($count) => $count->meal_date.':'.$count->meal_type_id);
        $meals = DB::table('meals')->join('meal_types', 'meal_types.id', '=', 'meals.meal_type_id')
            ->where('meals.event_id', $event->id)
            ->select('meals.id', 'meals.name', DB::raw('DATE(meals.date) as meal_date'), 'meals.meal_type_id', 'meal_types.name as type_name', 'meals.starts_at', 'meals.ends_at');

        return MealClaimDay::scope(DB::query()->fromSub($meals, 'options'), $event)
            ->orderBy('meal_date')->orderBy('starts_at')->orderBy('id')->get()->map(function ($meal) use ($counts) {
                $count = $counts->get($meal->meal_date.':'.$meal->meal_type_id);
                $meal->available = (int) ($count?->remaining ?? 0) === 0;
                $meal->extra = (int) ($count?->normal_total ?? 0) > 0;

                return $meal;
            });
    }
}
