<?php

namespace App\Http\Requests\Kitchen;

use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;

class IndexMealOverridesRequest extends FormRequest
{
    protected function ability(): string
    {
        return 'meals.override';
    }

    public function authorize(): bool
    {
        $event = app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        abort_unless((int) $this->route('member')->event_id === (int) $event->id && $this->route('member')->status === 'hired', 404);

        return $this->user()->can($this->ability(), $event);
    }

    public function rules(): array
    {
        return [];
    }
}
