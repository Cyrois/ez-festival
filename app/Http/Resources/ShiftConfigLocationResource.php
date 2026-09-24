<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftConfigLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $templates = $this->whenLoaded('shiftTemplates', fn () => $this->shiftTemplates, collect());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'template_count' => $templates->count(),
        ];
    }
}
