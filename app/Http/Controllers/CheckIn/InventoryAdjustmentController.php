<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\AdjustInventoryRequest;
use App\Http\Requests\CheckIn\InventoryAdjustmentOptionsRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\InventoryReceiverResource;
use App\Models\Event;
use App\Models\StockHolder;
use App\Queries\InventoryHoldingsQuery;
use App\Queries\InventoryQuery;
use App\Services\InventoryMovementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryAdjustmentController extends Controller
{
    public function index(InventoryAdjustmentOptionsRequest $request, EventContext $context, InventoryHoldingsQuery $query): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());
        $people = $query->adjustmentPeople($event, $request->validated('direction'), $request->validated('search') ?? '');

        return InventoryReceiverResource::collection($people->paginate(25));
    }

    public function show(InventoryAdjustmentOptionsRequest $request, EventContext $context, InventoryQuery $query): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $holder = StockHolder::where('event_id', $event->id)->where('person_id', $request->integer('person_id'))->first();
        $rows = $holder === null ? collect() : $query->itemRows($event->id, $query->items($event->id, $holder->id)->get());

        return new InventoryDataTableResource(['draw' => 0, 'total' => $rows->count(), 'filtered' => $rows->count(), 'rows' => $rows]);
    }

    public function store(AdjustInventoryRequest $request, Event $event, EventContext $context, InventoryMovementService $movements): RedirectResponse
    {
        $context->requireCurrentEvent($request->user(), $event, writable: true);
        $movements->adjustInventory($event, $request->user(), $request->validated());

        return back();
    }
}
