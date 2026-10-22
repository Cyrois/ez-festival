<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TeamHoursReportValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamHoursReportResource extends JsonResource
{
    use TeamHoursReportValues;

    public function __construct(
        Collection $groups,
        private readonly int $eventId,
        private readonly ?string $startDate,
        private readonly ?string $endDate,
        private readonly string $employmentType,
    ) {
        parent::__construct($groups);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'employment_type' => $this->employmentType,
            'range' => ['start_date' => $this->startDate, 'end_date' => $this->endDate],
            // Sum the same SQL aggregates shown in the rows, in the same snapshot.
            'totals' => $this->hourTotals($this->resource),
            'groups' => $this->resource->map(fn (array $group): array => [
                'group_id' => $group['group_id'],
                'name' => $group['group_name'],
                ...$this->hours($group),
            ])->values()->all(),
        ];
    }
}
