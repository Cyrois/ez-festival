<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function __construct($resource, private readonly string $area, private readonly Event $event)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            ...($request->user()->can($this->area.'.personal_info', $this->event) ? ['email' => $this->email, 'phone' => $this->phone] : ['personal_info_hidden' => true]),
            'is_primary' => (bool) ($this->pivot?->is_primary ?? false),
        ];
    }
}
