<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TeamHoursReportValues;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamShiftHoursReportResource extends JsonResource
{
    use TeamHoursReportValues;

    public function __construct(Collection $shifts, private readonly int $eventId, private readonly string $day, private readonly bool $canViewScheduling, private readonly string $employmentType)
    {
        parent::__construct($shifts);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'employment_type' => $this->employmentType,
            'date' => $this->day,
            'shifts' => $this->resource->map(fn (object $shift): array => [
                'shift_id' => (int) $shift->shift_id,
                'name' => $shift->name,
                'shift_url' => $this->canViewScheduling ? route('team.shifts.show', $shift->shift_id) : null,
                'location' => $shift->location,
                'starts_at' => CarbonImmutable::parse($shift->starts_at, 'UTC')->format('Y-m-d\TH:i'),
                'ends_at' => CarbonImmutable::parse($shift->ends_at, 'UTC')->format('Y-m-d\TH:i'),
                'people_count' => (int) $shift->people_count,
                'supervisor_name' => $shift->supervisor_name,
                ...$this->hours($shift),
            ])->values()->all(),
        ];
    }
}
