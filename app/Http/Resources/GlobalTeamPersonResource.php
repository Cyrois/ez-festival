<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalTeamPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'can_log_in' => $this->can_log_in,
            'is_admin' => $this->relationLoaded('user') && (bool) $this->user?->is_admin,
            'events' => $this->whenLoaded('teamEngagements', fn () => $this->teamEngagements->map(fn ($engagement): array => [
                'event_id' => $engagement->event_id,
                'event_name' => $engagement->event->name,
                'role_id' => $engagement->role_id,
                'role_name' => $engagement->role->name,
                'role_active' => $engagement->role->active,
            ])->values()->all()),
        ];
    }
}
