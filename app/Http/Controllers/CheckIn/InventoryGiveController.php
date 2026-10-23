<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryBatchRequest;
use App\Models\Event;
use App\Services\InventoryMovementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class InventoryGiveController extends Controller
{
    public function store(InventoryBatchRequest $request, Event $event, EventContext $context, InventoryMovementService $movements): RedirectResponse
    {
        $context->requireCurrentEvent($request->user(), $event, writable: true);
        $movements->give($event, $request->user(), $request->validated());

        return back();
    }
}
