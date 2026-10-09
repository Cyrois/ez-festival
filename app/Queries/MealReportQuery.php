<?php

namespace App\Queries;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealReportQuery
{
    public function __construct(private MealEntitlementQuery $entitlements) {}

    /** One row per named meal, aggregated in one snapshot without loading grants or people. */
    public function rows(Event $event): Collection
    {
        $projected = $this->entitlements->forEvent($event)
            ->select('grants.meal_id')->selectRaw('COUNT(*) as projected')
            ->groupBy('grants.meal_id');

        // Retain claims even after their Scheduling source is removed. Group by the
        // stable named meal ID; saved claim metadata remains on each assignment.
        $used = DB::table('meal_assignments')->where('event_id', $event->id)->whereNotNull('claimed_at')
            ->select('meal_id')->selectRaw('COUNT(*) as used, SUM(CASE WHEN is_override THEN 1 ELSE 0 END) as extras')
            ->groupBy('meal_id');

        return DB::table('meals')->where('meals.event_id', $event->id)
            ->join('meal_types', 'meal_types.id', '=', 'meals.meal_type_id')
            ->where('meal_types.event_id', $event->id)
            ->leftJoinSub($projected, 'projection', 'projection.meal_id', '=', 'meals.id')
            ->leftJoinSub($used, 'claims', 'claims.meal_id', '=', 'meals.id')
            ->select('meals.id as meal_id', 'meals.name as meal_name', 'meals.meal_type_id', 'meal_types.name as meal_type')
            ->selectRaw('DATE(meals.date) as meal_date, COALESCE(projection.projected, 0) as projected, COALESCE(claims.used, 0) as used, COALESCE(claims.extras, 0) as extras')
            ->orderBy('meals.date')->orderBy('meal_types.starts_at')->orderBy('meal_types.ends_at')
            ->orderBy('meals.starts_at')->orderBy('meals.name')->orderBy('meals.id')
            ->get()->map(fn ($row): array => [
                'meal_id' => (int) $row->meal_id,
                'meal_name' => $row->meal_name,
                'date' => $row->meal_date,
                'meal_type_id' => (int) $row->meal_type_id,
                'meal_type' => $row->meal_type,
                'projected' => (int) $row->projected,
                'used' => (int) $row->used,
                'remaining' => max((int) $row->projected - (int) $row->used, 0),
                'extras' => (int) $row->extras,
                'total' => (int) $row->projected - (int) $row->used - (int) $row->extras,
            ]);
    }
}
