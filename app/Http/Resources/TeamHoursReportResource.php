<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamHoursReportResource extends JsonResource
{
    public function __construct(
        Collection $groups,
        private readonly int $eventId,
        private readonly ?string $startDate,
        private readonly ?string $endDate,
    ) {
        parent::__construct($groups);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'range' => ['start_date' => $this->startDate, 'end_date' => $this->endDate],
            // Sum the same SQL aggregates shown in the rows, in the same snapshot.
            'totals' => $this->hours((int) $this->resource->sum('scheduled_minutes')),
            'groups' => $this->resource->map(fn (array $group): array => [
                'group_id' => $group['group_id'],
                'name' => $group['group_name'],
                ...$this->hours($group['scheduled_minutes']),
            ])->values()->all(),
        ];
    }

    private function hours(int $scheduledMinutes): array
    {
        return [
            'scheduled_minutes' => $scheduledMinutes,
            'approved_minutes' => null,
            'unapproved_minutes' => null,
        ];
    }
}
