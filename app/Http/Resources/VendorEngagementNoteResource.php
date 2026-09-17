<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorEngagementNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->user?->name ?? $this->user?->email ?? __('vendors.notes_author_unknown'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
