<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class CheckInShowResource extends JsonResource
{
    protected function engagementData(Request $request, string $permission, string $type, string $name): array
    {
        $canSeePersonalInfo = $request->user()->can($permission, $this->event);
        $assignments = $this->passAssignments
            ->whereNotNull('person_id')
            ->groupBy('person_id');

        $people = $this->people->map(function ($person) use ($assignments, $canSeePersonalInfo): array {
            $held = $assignments->get($person->id, collect());
            $entitlements = $held->flatMap(function ($assignment): Collection {
                return $assignment->expectedEntitlements->map(function ($expected) use ($assignment): array {
                    $issued = $expected->issuedEntitlement;
                    $locations = $expected->entitlementItem->adjustments
                        ->filter(fn ($stock) => $stock->location !== null)
                        ->sortBy('location.name')
                        ->values()
                        ->map(fn ($stock): array => [
                            'id' => $stock->location_id,
                            'name' => $stock->location->name,
                            'in_stock' => (int) $stock->balance,
                        ]);

                    return [
                        'id' => $expected->id,
                        'name' => $expected->entitlementItem->name,
                        'source' => $assignment->passType->name,
                        'status' => $issued ? 'issued' : 'pending',
                        'locations' => $locations,
                        'issued' => $issued ? [
                            'location' => $issued->location?->name,
                            'code' => $issued->code,
                            'issued_at' => $issued->issued_at,
                        ] : null,
                    ];
                });
            })->sortBy('name')->values();

            return [
                'id' => $person->id,
                'name' => $person->name,
                ...($canSeePersonalInfo ? ['email' => $person->email] : ['personal_info_hidden' => true]),
                'is_primary' => (bool) $person->pivot->is_primary,
                'passes' => $held->pluck('passType.name')->unique()->values(),
                'pass_labels' => $held->flatMap(fn ($assignment) => $assignment->passType->labels)
                    ->unique('id')
                    ->sortBy('name')
                    ->values()
                    ->map->only('name', 'color'),
                'issued' => $entitlements->where('status', 'issued')->count(),
                'expected' => $entitlements->count(),
                'entitlements' => $entitlements,
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
