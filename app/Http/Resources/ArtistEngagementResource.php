<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistEngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'artist_id' => $this->artist_id,
            'name' => $this->artist->name,
            'status' => $this->status,
            'artist_type_id' => $this->artist_type_id,
            'type' => $this->artistType?->name,
            'labels' => $this->artist->labels->map->only(['id', 'name', 'color'])->values(),
            // TODO: Read filled values from the shared artist custom-field system when available.
            'custom' => [],
        ];
    }
}
