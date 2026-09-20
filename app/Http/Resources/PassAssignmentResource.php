<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pass_id' => $this->pass_id,
            'pass_name' => $this->whenLoaded('pass', fn (): string => $this->pass->name),
            'person' => $this->whenLoaded('person', fn (): ?array => $this->person === null
                ? null
                : (new PersonResource($this->person))->resolve()),
        ];
    }
}
