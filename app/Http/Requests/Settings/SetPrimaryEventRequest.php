<?php

namespace App\Http\Requests\Settings;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SetPrimaryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        if ($event === null || $this->user() === null) {
            return false;
        }

        $organization = $this->organization();

        if ((int) $event->organization_id !== (int) $organization->id) {
            return false;
        }

        /** @var User $user */
        $user = $this->user();
        $current = $user->effectiveEvent($organization);

        return $current === null || (int) $current->id !== (int) $event->id;
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
