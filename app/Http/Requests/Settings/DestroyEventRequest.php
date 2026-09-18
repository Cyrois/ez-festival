<?php

namespace App\Http\Requests\Settings;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class DestroyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        return $this->user() !== null && $event !== null && ! $event->isLocked();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
