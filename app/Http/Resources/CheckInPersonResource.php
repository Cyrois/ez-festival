<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expected = (int) $this->expected_count;
        $issued = (int) $this->issued_count;

        return [
            'person_id' => (int) $this->person_id,
            'engagement_id' => (int) $this->engagement_id,
            'name' => $this->person_name,
            ...($request->user()->can(match ($this->type) {
                'artist' => 'artists.personal_info', 'vendor' => 'vendors.personal_info', 'team' => 'team.personal_info', default => 'patrons.personal_info'
            }) ? ['subtitle' => $this->person_email] : ['personal_info_hidden' => true]),
            'type' => $this->type,
            'context' => $this->context_name,
            'pass_name' => $this->pass_name ?? '',
            'issued' => $issued,
            'expected' => $expected,
            'check_in_status' => $this->status($issued, $expected),
            'can_edit' => (bool) $this->can_edit,
        ];
    }

    private function status(int $issued, int $expected): string
    {
        return match (true) {
            $expected === 0, $issued >= $expected => 'complete',
            $issued === 0 => 'not_started',
            default => 'partial',
        };
    }
}
