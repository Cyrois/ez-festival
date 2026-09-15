<?php

namespace App\Http\Resources;

use App\Models\Event;

class EventResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Event $event, ?int $activeEventId): array
    {
        $isActive = $activeEventId !== null && (int) $activeEventId === (int) $event->id;
        $isLocked = $event->isLocked();

        return [
            'id' => $event->id,
            'name' => $event->name,
            'starts_on' => $event->starts_on->toDateString(),
            'ends_on' => $event->ends_on->toDateString(),
            'timezone' => $event->timezone,
            'is_locked' => $isLocked,
            'is_active' => $isActive,
            'is_past' => $event->isPast(),
            'is_read_only' => $isLocked,
        ];
    }
}
