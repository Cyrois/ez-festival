<?php

namespace App\Http\Requests\Settings\Concerns;

use App\Support\EventContext;

trait EventLocationRules
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');

        if ($user === null || ! $user->can('manage-event-locations', $event)) {
            return false;
        }
        if (! $user->isAdmin()) {
            app(EventContext::class)->requireCurrentEvent($user, $event);
        }
        $location = $this->route('location');
        abort_if($location !== null && (int) $location->event_id !== (int) $event->id, 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Missing also refuses an empty/null field, so a rename cannot erase a type.
            'type' => $this->user()->isAdmin() ? ['nullable', 'string', 'max:255'] : ['missing'],
        ];
    }
}
