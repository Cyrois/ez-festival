<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCurrentEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

class CurrentEventController extends Controller
{
    public function update(UpdateCurrentEventRequest $request, Event $event): RedirectResponse
    {
        $request->user()->setCurrentEvent($event);

        return redirect()->route('events.index');
    }
}
