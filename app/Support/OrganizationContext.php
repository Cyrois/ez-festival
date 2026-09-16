<?php

namespace App\Support;

use App\Models\ApplicationState;
use App\Models\Event;

class OrganizationContext
{
    public function name(): string
    {
        return (string) config('organization.name', 'Festival');
    }

    public function state(): ApplicationState
    {
        return ApplicationState::query()->firstOrCreate(['id' => 1]);
    }

    public function defaultEvent(): ?Event
    {
        return $this->state()->defaultEvent;
    }

    public function setupIsComplete(): bool
    {
        return $this->state()->setup_completed_at !== null;
    }

    public function markSetupComplete(): void
    {
        $state = $this->state();

        if ($state->setup_completed_at === null) {
            $state->forceFill(['setup_completed_at' => now()])->save();
        }
    }

    public function setDefaultEvent(Event $event): void
    {
        $this->state()->forceFill(['default_event_id' => $event->id])->save();
    }
}
