<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

final class EventContext
{
    public function current(User $user): ?Event
    {
        return $user->effectiveEvent();
    }

    public function requireCurrent(User $user): Event
    {
        return $this->current($user) ?? abort(404);
    }

    public function requireWritable(User $user): Event
    {
        $event = $this->requireCurrent($user);
        $event->ensureWritable();

        return $event;
    }

    public function requireCurrentEvent(User $user, Event $event, bool $writable = false): Event
    {
        abort_unless($this->current($user)?->is($event), 404);

        if ($writable) {
            $event->ensureWritable();
        }

        return $event;
    }
}
