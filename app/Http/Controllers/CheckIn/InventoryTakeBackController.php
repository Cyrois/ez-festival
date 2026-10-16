<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ManageInventoryRequest;
use App\Http\Requests\CheckIn\TakeBackStockRequest;
use App\Http\Resources\InventoryReceiverResource;
use App\Models\Event;
use App\Models\StockHolder;
use App\Queries\InventoryTargetsQuery;
use App\Services\InventoryMovementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryTakeBackController extends Controller
{
    public function index(ManageInventoryRequest $request, StockHolder $holder, EventContext $context, InventoryTargetsQuery $targets): AnonymousResourceCollection
    {
        $context->requireCurrentEvent($request->user(), $holder->event);
        abort_if($holder->person_id === null, 404);

        return InventoryReceiverResource::collection($targets->receivers($holder->event, $holder->person_id, $request->validated('search') ?? '')->paginate(25));
    }

    public function store(TakeBackStockRequest $request, Event $event, StockHolder $holder, EventContext $context, InventoryMovementService $movements): RedirectResponse
    {
        $context->requireCurrentEvent($request->user(), $event, writable: true);
        abort_unless($holder->event_id === $event->id && $holder->person_id !== null, 404);
        $movements->takeBack($event, $request->user(), $holder, $request->validated());

        return back();
    }
}
