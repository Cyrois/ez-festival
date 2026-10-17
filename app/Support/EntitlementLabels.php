<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EntitlementLabels
{
    /** @return array<int, array{id: int, name: string, color: string}> */
    public static function ordered(iterable $labels): array
    {
        return collect($labels)->sort(function ($left, $right): int {
            return strnatcasecmp(Str::ascii($left->name), Str::ascii($right->name))
                ?: $left->id <=> $right->id;
        })->map(fn ($label): array => [
            'id' => (int) $label->id, 'name' => $label->name, 'color' => $label->color,
        ])->values()->all();
    }

    /** Batch labels for query-builder stock rows without multiplying balances or pagination. */
    public static function forItems(int $eventId, iterable $itemIds): Collection
    {
        $ids = collect($itemIds)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('entitlement_item_labels as labels')
            ->join('entitlement_item_label_assignments as assignments', 'assignments.entitlement_item_label_id', '=', 'labels.id')
            ->where('labels.event_id', $eventId)
            ->whereIn('assignments.entitlement_item_id', $ids)
            ->get(['assignments.entitlement_item_id', 'labels.id', 'labels.name', 'labels.color'])
            ->groupBy('entitlement_item_id')->map(fn ($labels): array => self::ordered($labels));
    }
}
