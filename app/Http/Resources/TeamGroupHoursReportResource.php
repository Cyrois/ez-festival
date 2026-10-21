<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TeamHoursReportValues;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamGroupHoursReportResource extends JsonResource
{
    use TeamHoursReportValues;

    public function __construct(
        Collection $people,
        private readonly int $eventId,
        private readonly Group $group,
        private readonly ?string $startDate,
        private readonly ?string $endDate,
        private readonly bool $canViewTeam,
        private readonly string $employmentType,
    ) {
        parent::__construct($people);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'employment_type' => $this->employmentType,
            'range' => ['start_date' => $this->startDate, 'end_date' => $this->endDate],
            'group' => ['group_id' => $this->group->id, 'name' => $this->group->name],
            'totals' => $this->hourTotals($this->resource),
            'people' => $this->resource->map(fn (object $person): array => [
                'team_engagement_id' => (int) $person->team_engagement_id,
                'person_id' => (int) $person->person_id,
                'name' => $person->name,
                'employment_type' => $person->employment_type,
                'hourly_pay' => $this->hourlyPay($person),
                'member_url' => $this->canViewTeam ? route('team.members.show', $person->team_engagement_id) : null,
                ...$this->hours($person),
            ])->values()->all(),
        ];
    }
}
