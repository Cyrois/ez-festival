<?php

namespace App\Http\Requests\Kitchen\Concerns;

use App\Support\EventContext;

trait AuthorizesMeal
{
    public function authorize(): bool
    {
        $event = app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        $meal = $this->route('meal');
        abort_if($meal !== null && (int) $meal->event_id !== (int) $event->id, 404);

        return $this->user()->can('meals.edit', $event);
    }
}
