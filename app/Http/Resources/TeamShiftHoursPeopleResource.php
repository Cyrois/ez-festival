<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TeamShiftHoursPeopleResource extends JsonResource
{
    public function __construct(Collection $people, private readonly int $eventId, private readonly int $shiftId, private readonly bool $canViewTeam)
    {
        parent::__construct($people);
    }

    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->eventId,
            'shift_id' => $this->shiftId,
            'people' => $this->resource->map(fn (object $person): array => [
                'team_engagement_id' => (int) $person->team_engagement_id,
                'person_id' => (int) $person->person_id,
                'name' => $person->name,
                'employment_type' => $person->employment_type,
                'is_supervisor' => (bool) $person->is_supervisor,
                'member_url' => $this->canViewTeam ? route('team.members.show', $person->team_engagement_id) : null,
                'scheduled_minutes' => (int) $person->scheduled_minutes,
                'approved_minutes' => null,
                'unapproved_minutes' => null,
            ])->values()->all(),
        ];
    }
}
