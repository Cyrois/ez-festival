<?php

namespace App\Http\Resources;

use App\Models\TeamEngagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class CheckInShowResource extends JsonResource
{
    protected function engagementData(Request $request, string $permission, string $type, string $name): array
    {
        $canSeePersonalInfo = $request->user()->can($permission, $this->event);
        $assignments = $this->passAssignments
            ->whereNotNull('person_id')
            ->filter(fn ($assignment) => $assignment->passType->event_id === $this->event_id)
            ->groupBy('person_id');

        $isTeam = $this->resource instanceof TeamEngagement;
        $holders = $isTeam ? collect([$this->person]) : $this->people;
        $people = $holders->map(function ($person) use ($assignments, $canSeePersonalInfo, $isTeam): array {
            $held = $assignments->get($person->id, collect());

            return [
                'id' => $person->id,
                'name' => $person->name,
                ...($canSeePersonalInfo ? ['email' => $person->email] : ['personal_info_hidden' => true]),
                'is_primary' => ! $isTeam && (bool) $person->pivot->is_primary,
                'has_pass' => $held->isNotEmpty(),
                'passes' => $held->pluck('passType.name')->unique()->values(),
                'pass_labels' => $held->flatMap(fn ($assignment) => $assignment->passType->labels)
                    ->unique('id')
                    ->sortBy('name')
                    ->values()
                    ->map->only('name', 'color'),
                'issued' => (int) $held->sum('issued_count'),
                'expected' => (int) $held->sum('expected_entitlements_count'),
            ];
        })->values();

        return [
            'id' => $this->id,
            'name' => $name,
            'type' => $type,
            'people' => $people,
        ];
    }
}
