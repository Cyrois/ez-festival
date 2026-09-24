<?php

namespace App\Http\Resources;

use App\Models\ArtistEngagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ArtistCheckInShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isArtist = $this->resource instanceof ArtistEngagement;
        $balancesByItem = $this->balancesByItemId();
        $assignments = $this->passAssignments
            ->whereNotNull('person_id')
            ->groupBy('person_id');

        $people = $this->people->map(function ($person) use ($assignments, $balancesByItem): array {
            $held = $assignments->get($person->id, collect());
            $entitlements = $held->flatMap(function ($assignment) use ($balancesByItem): Collection {
                return $assignment->expectedEntitlements->map(function ($expected) use ($assignment, $balancesByItem): array {
                    $issued = $expected->issuedEntitlement;
                    $balances = $balancesByItem[$expected->entitlement_item_id] ?? [];
                    $locations = $expected->entitlementItem->adjustments
                        ->pluck('location')
                        ->filter()
                        ->unique('id')
                        ->filter(fn ($location) => ($balances[$location->id] ?? 0) > 0)
                        ->sortBy('name')
                        ->values()
                        ->map(fn ($location): array => [
                            'id' => $location->id,
                            'name' => $location->name,
                            'in_stock' => $balances[$location->id],
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
                'email' => $person->email,
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
            'name' => $isArtist ? $this->artist->name : $this->vendor->name,
            'type' => $isArtist ? 'artist' : 'vendor',
            'people' => $people,
        ];
    }

    /** @return array<int, array<int, int>> */
    private function balancesByItemId(): array
    {
        $cache = [];

        foreach ($this->passAssignments as $assignment) {
            foreach ($assignment->expectedEntitlements as $expected) {
                $item = $expected->entitlementItem;
                if (isset($cache[$item->id])) {
                    continue;
                }

                $cache[$item->id] = $item->adjustments
                    ->whereNotNull('location_id')
                    ->groupBy('location_id')
                    ->map(fn (Collection $rows): int => (int) $rows->sum('delta'))
                    ->all();
            }
        }

        return $cache;
    }
}
