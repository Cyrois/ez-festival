<?php

namespace App\Queries;

use App\Models\Event;
use App\Support\MealClaimDay;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealEntitlementQuery
{
    /** Current unused grants plus durable used rows, including sources removed from Scheduling. */
    public function forPerson(Event $event, int $memberId): Builder
    {
        $unused = $this->forEvent($event)->where('team_engagements.id', $memberId)
            ->join('meal_types', 'meal_types.id', '=', 'meals.meal_type_id')
            ->join('locations', 'locations.id', '=', 'shifts.location_id')
            ->whereNotExists(fn (Builder $claims) => $claims->selectRaw('1')->from('meal_claims')
                ->whereColumn('meal_claims.team_engagement_id', 'team_engagements.id')
                ->whereColumn('meal_claims.source_shift_id', 'shifts.id')->whereColumn('meal_claims.meal_id', 'meals.id'))
            ->select('shift_meals.id as shift_meal_id', 'shifts.id as source_shift_id', 'meals.id as meal_id',
                'meals.name as meal_name', DB::raw('DATE(meals.date) as meal_date'), 'meals.meal_type_id', 'meal_types.name as type_name',
                'meals.starts_at', 'meals.ends_at', 'locations.name as shift_location_name',
                'shifts.starts_at as shift_starts_at', 'shifts.ends_at as shift_ends_at')
            ->selectRaw('NULL as claim_id, NULL as claimed_at, 0 as used');
        $used = DB::table('meal_claims')->join('meal_types', 'meal_types.id', '=', 'meal_claims.meal_type_id')
            ->where('meal_claims.event_id', $event->id)->where('team_engagement_id', $memberId)
            ->select('shift_meal_id', 'source_shift_id', 'meal_id', 'meal_name', DB::raw('DATE(meal_claims.meal_date) as meal_date'),
                'meal_type_id', 'meal_types.name as type_name', 'meal_claims.starts_at', 'meal_claims.ends_at',
                'shift_location_name', 'shift_starts_at', 'shift_ends_at', 'meal_claims.id as claim_id', 'claimed_at')
            ->selectRaw('1 as used');

        return MealClaimDay::scope(DB::query()->fromSub($unused->unionAll($used), 'person_meals'), $event);
    }

    public function personCounts(Event $event, int $memberId): Collection
    {
        return $this->forPerson($event, $memberId)->select('meal_date', 'meal_type_id', 'type_name')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN used = 0 THEN 1 ELSE 0 END) as remaining')
            ->groupBy('meal_date', 'meal_type_id', 'type_name')->orderBy('meal_date')->orderBy('meal_type_id')->get();
    }

    /** One row per shift meal and listed person; deliberately never deduplicated by meal. */
    public function forEvent(Event $event): Builder
    {
        return DB::table('shift_meal_people as grants')
            ->join('shift_meals', 'shift_meals.id', '=', 'grants.shift_meal_id')
            ->join('shifts', 'shifts.id', '=', 'shift_meals.shift_id')
            ->join('meals', 'meals.id', '=', 'shift_meals.meal_id')
            ->join('shift_assignments', function ($join): void {
                $join->on('shift_assignments.id', '=', 'grants.shift_assignment_id')
                    ->on('shift_assignments.shift_id', '=', 'shifts.id');
            })
            ->join('team_engagements', 'team_engagements.id', '=', 'shift_assignments.team_engagement_id')
            ->where('shifts.event_id', $event->id)->where('meals.event_id', $event->id)
            ->where('team_engagements.event_id', $event->id)
            ->select('shift_meals.id as shift_meal_id', 'shift_meals.meal_id', 'shifts.id as shift_id',
                'shift_assignments.id as assignment_id', 'team_engagements.id as team_engagement_id', 'team_engagements.person_id');
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
        return $this->forEvent($event)->select('shift_meals.meal_id')->selectRaw('COUNT(*) as quantity')
            ->groupBy('shift_meals.meal_id')->pluck('quantity', 'meal_id');
    }
}
