<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamShiftHoursReportResource extends JsonResource
{
    public function __construct(Collection $shifts, private readonly int $eventId, private readonly string $day)
    {
        parent::__construct($shifts);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'date' => $this->day,
            'shifts' => $this->resource->map(fn (object $shift): array => [
                'shift_id' => (int) $shift->shift_id,
                'name' => $shift->name,
                'location' => $shift->location,
                'starts_at' => CarbonImmutable::parse($shift->starts_at, 'UTC')->format('Y-m-d\TH:i'),
                'ends_at' => CarbonImmutable::parse($shift->ends_at, 'UTC')->format('Y-m-d\TH:i'),
                'people_count' => (int) $shift->people_count,
                'supervisor_name' => $shift->supervisor_name,
                'scheduled_minutes' => (int) $shift->scheduled_minutes,
                'approved_minutes' => null,
                'unapproved_minutes' => null,
            ])->values()->all(),
        ];
    }
}
