<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamEngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->person->name,
            'email' => $this->person->email,
            'phone' => $this->person->phone,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'hourly_pay' => $this->hourly_pay,
            'group_id' => $this->group_id,
            'group' => $this->group?->only(['id', 'name']),
        ];
    }
}
