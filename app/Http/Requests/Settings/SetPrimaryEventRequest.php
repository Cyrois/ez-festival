<?php

namespace App\Http\Requests\Settings;

use App\Models\Event;
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

        /** @var User $user */
        $user = $this->user();
        $current = $user->effectiveEvent();

        return $current === null || (int) $current->id !== (int) $event->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
