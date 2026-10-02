<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $area = match (true) {
            $this->artist_engagement_id !== null => 'artists',
            $this->vendor_engagement_id !== null => 'vendors',
            $this->team_engagement_id !== null => 'team',
            default => 'patrons',
        };

        return [
            'id' => $this->id,
            'pass_type_id' => $this->pass_type_id,
            'pass_name' => $this->whenLoaded('passType', fn (): string => $this->passType->name),
            'person' => $this->whenLoaded('person', fn (): ?array => $this->person === null
                ? null
                : (new PersonResource($this->person, $area, $this->passType->event))->resolve($request)),
        ];
    }
}
