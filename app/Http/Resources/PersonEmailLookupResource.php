<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonEmailLookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'exists' => (bool) $this->resource['person'],
            'on_global_team' => (bool) $this->resource['on_global_team'],
            'person' => $this->when(
                $this->resource['person'] !== null,
                fn (): array => [
                    'id' => $this->resource['person']->id,
                    'name' => $this->resource['person']->name,
                ],
            ),
        ];
    }
}
