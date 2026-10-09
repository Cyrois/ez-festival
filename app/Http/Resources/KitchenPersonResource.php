<?php

namespace App\Http\Resources;

use App\Support\MealClaimDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class KitchenPersonResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $timezone = $this->resource['timezone'];
        $event = $this->resource['event'];

        return [
            'draw' => $this->resource['draw'], 'recordsTotal' => $this->resource['total'], 'recordsFiltered' => $this->resource['total'],
            'person' => $this->resource['person'], 'today' => now($timezone)->toDateString(),
            'can_claim' => $this->resource['can_claim'], 'is_locked' => $this->resource['is_locked'],
            'can_override' => $this->resource['can_override'], 'can_remove_override' => $this->resource['can_remove_override'],
            'counts' => $this->resource['counts']->map(fn ($count) => [
                'date' => $count->meal_date, 'type_id' => (int) $count->meal_type_id, 'type' => $count->type_name,
                'total' => (int) $count->total, 'left' => (int) $count->remaining,
                'overrides' => (int) $count->overrides,
            ]),
            'data' => $this->resource['rows']->map(fn ($row) => [
                'assignment_id' => (int) $row->assignment_id, 'meal_id' => (int) $row->meal_id,
                'source_shift_id' => $row->source_shift_id === null ? null : (int) $row->source_shift_id,
                'name' => $row->meal_name, 'date' => $row->meal_date, 'type' => $row->type_name,
                'starts_at' => substr($row->starts_at, 0, 5), 'ends_at' => substr($row->ends_at, 0, 5),
                'shift_location' => $row->shift_location_name,
                'shift_start' => $row->shift_starts_at === null ? null : Carbon::parse($row->shift_starts_at, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i'),
                'shift_end' => $row->shift_ends_at === null ? null : Carbon::parse($row->shift_ends_at, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i'),
                'used' => (bool) $row->used, 'claim_token' => $row->claim_token,
                'eligible_today' => MealClaimDay::allows($event, $row->meal_date, $row->starts_at, $row->ends_at),
                'is_override' => (bool) $row->is_override,
                'override_at' => $row->override_given_at === null ? null : Carbon::parse($row->override_given_at, 'UTC')->timezone($timezone)->format('H:i'),
                'used_at' => $row->claimed_at === null ? null : Carbon::parse($row->claimed_at, 'UTC')->timezone($timezone)->format('H:i'),
            ]),
        ];
    }
}
