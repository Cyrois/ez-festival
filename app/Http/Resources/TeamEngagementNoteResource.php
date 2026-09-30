<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamEngagementNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->user?->name ?? $this->user?->email ?? __('team.member.notes_author_unknown'),
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'editable_until' => (int) $this->user_id === (int) $request->user()?->id
                ? $this->created_at?->copy()->addMinutes(5)->toIso8601String()
                : null,
        ];
    }
}
