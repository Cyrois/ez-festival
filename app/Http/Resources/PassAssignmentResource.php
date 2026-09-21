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
            'pass_type_id' => $this->pass_type_id,
            'pass_name' => $this->whenLoaded('passType', fn (): string => $this->passType->name),
            'person' => $this->whenLoaded('person', fn (): ?array => $this->person === null
                ? null
                : (new PersonResource($this->person))->resolve()),
        ];
    }
}
