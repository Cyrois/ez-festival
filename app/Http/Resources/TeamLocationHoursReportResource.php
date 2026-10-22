<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TeamHoursReportValues;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamLocationHoursReportResource extends JsonResource
{
    use TeamHoursReportValues;

    public function __construct(
        Collection $rows,
        private readonly int $eventId,
        private readonly ?string $startDate,
        private readonly ?string $endDate,
        private readonly string $employmentType,
        private readonly ?Location $location = null,
        private readonly bool $canViewTeam = false,
        private readonly ?int $missingRateCount = null,
    ) {
        parent::__construct($rows);
    }

    public function toArray(Request $request): array
    {
        $totals = $this->hourTotals($this->resource);
        if ($this->missingRateCount !== null) {
            $totals['missing_rate_count'] = $this->missingRateCount;
        }

        return [
            'event_id' => $this->eventId,
            'employment_type' => $this->employmentType,
            'range' => ['start_date' => $this->startDate, 'end_date' => $this->endDate],
            'totals' => $totals,
            ...($this->location === null ? [
                'locations' => $this->resource->map(fn (object $row): array => [
                    'location_id' => (int) $row->location_id,
                    'name' => $row->name,
                    ...$this->hours($row),
                ])->values()->all(),
            ] : [
                'location' => ['location_id' => $this->location->id, 'name' => $this->location->name],
                'people' => $this->resource->map(fn (object $person): array => [
                    'team_engagement_id' => (int) $person->team_engagement_id,
                    'person_id' => (int) $person->person_id,
                    'name' => $person->name,
                    'employment_type' => $person->employment_type,
                    'hourly_pay' => $this->hourlyPay($person),
                    'member_url' => $this->canViewTeam ? route('team.members.show', $person->team_engagement_id) : null,
                    ...$this->hours($person),
                ])->values()->all(),
            ]),
        ];
    }
}
