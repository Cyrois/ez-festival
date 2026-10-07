<?php

namespace App\Queries;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealEntitlementQuery
{
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
