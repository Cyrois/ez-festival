<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class MealReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['days' => $this->resource->groupBy('date')->map(fn (Collection $meals, string $date): array => [
            'date' => $date,
            'meal_types' => $meals->groupBy('meal_type_id')->map(fn (Collection $group): array => [
                'meal_type_id' => $group->first()['meal_type_id'],
                'meal_type' => $group->first()['meal_type'],
                'meals' => $group->values()->all(),
                'totals' => $this->totals($group),
            ])->values()->all(),
            'totals' => $this->totals($meals),
        ])->values()->all()];
    }

    private function totals(Collection $meals): array
    {
        $counts = collect(['assigned', 'projected', 'used', 'extras'])
            ->mapWithKeys(fn (string $key): array => [$key => (int) $meals->sum($key)])->all();

        return [...$counts,
            'remaining' => max($counts['projected'] - $counts['used'], 0),
            'total' => $counts['projected'] - $counts['used'],
        ];
    }
}
