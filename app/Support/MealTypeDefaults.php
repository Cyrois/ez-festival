<?php

namespace App\Support;

final class MealTypeDefaults
{
    // Shared defaults for new events and the standard meal-type seeder.
    public const TYPES = [
        ['name' => 'Breakfast', 'starts_at' => '07:00:00', 'ends_at' => '10:00:00'],
        ['name' => 'Lunch', 'starts_at' => '11:00:00', 'ends_at' => '14:00:00'],
        ['name' => 'Dinner', 'starts_at' => '17:00:00', 'ends_at' => '20:00:00'],
        ['name' => 'Midnight', 'starts_at' => '23:00:00', 'ends_at' => '01:00:00'],
    ];

    public static function rows(int $eventId): array
    {
        return array_map(fn (array $type, int $order): array => [
            ...$type,
            'event_id' => $eventId,
            'name_key' => mb_strtolower($type['name']),
            'sort_order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ], self::TYPES, array_keys(self::TYPES));
    }
}
