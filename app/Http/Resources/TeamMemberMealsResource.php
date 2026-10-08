<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TeamMemberMealsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $this->resource['timezone'];

        return [
            'days' => $this->resource['rows']->groupBy('meal_date')->map(fn (Collection $rows, string $date): array => [
                'date' => $date,
                'counts' => $rows->groupBy('meal_type_id')->map(fn (Collection $typeRows): array => [
                    'type_id' => (int) $typeRows->first()->meal_type_id,
                    'type' => $typeRows->first()->type_name,
                    'total' => $typeRows->count(),
                    'used' => $typeRows->filter(fn ($row): bool => (bool) $row->used)->count(),
                ])->values()->all(),
                'rows' => $rows->map(fn ($row): array => [
                    'assignment_id' => (int) $row->assignment_id,
                    'meal_id' => (int) $row->meal_id,
                    'name' => $row->meal_name,
                    'type' => $row->type_name,
                    'date' => $row->meal_date,
                    'starts_at' => substr($row->starts_at, 0, 5),
                    'ends_at' => substr($row->ends_at, 0, 5),
                    'source_shift_id' => $row->source_shift_id === null ? null : (int) $row->source_shift_id,
                    'source_removed' => $row->source_shift_id !== null
                        && (! $row->is_active || $row->shift_meal_id === null || $row->shift_assignment_id === null),
                    'shift_location' => $row->shift_location_name,
                    'shift_start' => $row->shift_starts_at === null ? null : Carbon::parse($row->shift_starts_at, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i'),
                    'shift_end' => $row->shift_ends_at === null ? null : Carbon::parse($row->shift_ends_at, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i'),
                    'used' => (bool) $row->used,
                    'used_at' => $row->claimed_at === null ? null : Carbon::parse($row->claimed_at, 'UTC')->timezone($timezone)->format('H:i'),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
