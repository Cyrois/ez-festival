<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ReturnStockRequest;
use App\Models\Event;
use App\Services\InventoryMovementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class InventoryMovementController extends Controller
{
    public function update(ReturnStockRequest $request, Event $event, EventContext $context, InventoryMovementService $movements): RedirectResponse
    {
        $context->requireCurrentEvent($request->user(), $event, writable: true);
        $movements->returnStock($event, $request->user(), $request->validated());

        return back();
    }
}
