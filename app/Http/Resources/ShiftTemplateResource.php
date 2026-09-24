<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = [];

        if ($this->relationLoaded('roleLines')) {
            $roles = $this->roleLines
                ->map(fn ($line): array => [
                    'shift_role_id' => $line->shift_role_id,
                    'headcount' => (int) $line->headcount,
                    'name' => $line->relationLoaded('shiftRole')
                        ? $line->shiftRole?->name
                        : null,
                ])
                ->values()
                ->all();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_id' => $this->location_id,
            'location_name' => $this->when(
                $this->relationLoaded('location'),
                fn () => $this->location?->name,
            ),
            'needs' => $this->needs(),
            'roles' => $roles,
        ];
    }
}
