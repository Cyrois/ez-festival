<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
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

        $organization = $this->organization();

        return (int) $event->organization_id === (int) $organization->id
            && $event->isLocked();
    }

    public function organization(): Organization
    {
        /** @var User $user */
        $user = $this->user();

        return $user->primaryOrganization() ?? $user->ensureOrganization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
