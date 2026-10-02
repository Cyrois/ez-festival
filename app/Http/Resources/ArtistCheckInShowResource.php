<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ArtistCheckInShowResource extends CheckInShowResource
{
    public function toArray(Request $request): array
    {
        return $this->engagementData($request, 'artists.personal_info', 'artist', $this->artist->name);
    }
}
