<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;
use LogicException;

class OrganizationContext
{
    private ?Organization $resolved = null;

    public function name(): string
    {
        return $this->organization()->name;
    }

    /**
     * Singleton organization row for this organization database.
     */
    public function organization(): Organization
    {
        if ($this->resolved !== null && $this->resolved->exists) {
            return $this->resolved;
        }

        $organizations = Organization::query()->orderBy('id')->limit(2)->get();

        if ($organizations->count() > 1) {
            Log::critical('The tenant database contains more than one organization.', [
                'organization_ids' => $organizations->pluck('id')->all(),
            ]);

            throw new LogicException('The tenant database contains more than one organization.');
        }

        return $this->resolved = $organizations->first()
            ?? Organization::query()->createOrFirst(
                ['singleton' => true],
                ['name' => 'Festival'],
            );
    }

    public function defaultEvent(): ?Event
    {
        return $this->organization()->activeEvent()->first();
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
