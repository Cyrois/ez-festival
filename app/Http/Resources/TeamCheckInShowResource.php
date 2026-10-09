<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TeamCheckInShowResource extends CheckInShowResource
{
    public function toArray(Request $request): array
    {
        $data = $this->engagementData($request, 'team.personal_info', 'team', $this->person->name);
        if ($request->user()->can('team.personal_info', $this->event)) {
            $data['people'] = $data['people']->map(fn (array $person): array => [...$person, 'phone' => $this->person->phone]);
        }

        return [...$data, 'group_name' => $this->group?->name, 'role_name' => $this->role?->name];
    }
}
