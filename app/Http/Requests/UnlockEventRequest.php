<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class UnlockEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        if ($event === null || $this->user() === null) {
            return false;
        }

        $organization = $this->user()->primaryOrganization();

        if ($organization === null) {
            return false;
        }

        return (int) $event->organization_id === (int) $organization->id
            && $event->isLocked();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
