<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;

class OrganizationContext
{
    public function name(): string
    {
        return $this->organization()->name;
    }

    /**
     * Singleton organization row for this client database.
     */
    public function organization(): Organization
    {
        return Organization::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Festival'],
        );
    }

    public function defaultEvent(): ?Event
    {
        return $this->organization()->activeEvent;
    }

    public function setupIsComplete(): bool
    {
        return $this->organization()->setupIsComplete();
    }

    public function markSetupComplete(): void
    {
        $this->organization()->markSetupComplete();
    }

    public function setDefaultEvent(Event $event): void
    {
        $this->organization()->forceFill(['active_event_id' => $event->id])->save();
    }
}
