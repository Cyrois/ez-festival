<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistCheckInListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expected = (int) $this->expected_count;
        $issued = (int) $this->issued_count;

        return [
            'id' => $this->id,
            'name' => $this->artist->name,
            'contact' => $this->people->first()?->only('name', 'email'),
            'expected' => $expected,
            'issued' => $issued,
            'check_in_status' => $this->status($issued, $expected),
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
