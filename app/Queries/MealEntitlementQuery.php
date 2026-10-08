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
    /** Person-owned grants, with claim state stored on the same row. */
    public function forPerson(Event $event, int $memberId): Builder
    {
        $rows = DB::table('meal_assignments as grants')->join('meal_types', 'meal_types.id', '=', 'grants.meal_type_id')
            ->where('grants.event_id', $event->id)->where('grants.team_engagement_id', $memberId)
            ->where(fn (Builder $visible) => $visible->whereNotNull('grants.claimed_at')
                ->orWhere(fn (Builder $active) => $this->active($active)))
            ->select('grants.id as assignment_id', 'grants.shift_meal_id', 'grants.source_shift_id', 'grants.meal_id',
                'grants.meal_name', DB::raw('DATE(grants.meal_date) as meal_date'), 'grants.meal_type_id', 'meal_types.name as type_name',
                'grants.starts_at', 'grants.ends_at', 'grants.shift_location_name', 'grants.shift_starts_at', 'grants.shift_ends_at',
                'grants.claimed_at', 'grants.claim_token')
            ->selectRaw('CASE WHEN grants.claimed_at IS NULL THEN 0 ELSE 1 END as used');

        return MealClaimDay::scope(DB::query()->fromSub($rows, 'person_meals'), $event);
    }

    /** Null source is a direct assignment; Scheduling removals retire only that source grant. */
    private function active(Builder $query): Builder
    {
        return $query->where('grants.is_active', true)->where(fn (Builder $source) => $source
            ->whereNull('grants.source_shift_id')->orWhere(fn (Builder $linked) => $linked
            ->whereNotNull('grants.shift_meal_id')->whereNotNull('grants.shift_assignment_id')));
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
        return $this->active(DB::table('meal_assignments as grants'))
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
}
