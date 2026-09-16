<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistEngagementNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->user?->name ?? $this->user?->email,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
