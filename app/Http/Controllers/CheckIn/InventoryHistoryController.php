<?php

namespace App\Http\Controllers\CheckIn;

use App\Enums\StockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryHistoryRequest;
use App\Http\Resources\EntitlementItemOptionResource;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\StockMovementResource;
use App\Models\EntitlementItem;
use App\Queries\InventoryHistoryQuery;
use App\Queries\InventoryQuery;
use Inertia\Inertia;
use Inertia\Response;

class InventoryHistoryController extends Controller
{
    public function index(InventoryHistoryRequest $request, InventoryQuery $inventory): Response
    {
        $event = $request->event();

        return Inertia::render('CheckIn/History', [
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'items' => EntitlementItemOptionResource::collection($inventory->itemOptions($event))->resolve(),
            'movementTypes' => array_column(StockMovement::cases(), 'value'),
        ]);
    }

    public function show(InventoryHistoryRequest $request, InventoryHistoryQuery $history, ?EntitlementItem $entitlementItem = null): InventoryDataTableResource
    {
        $event = $request->event();
        $itemId = $request->item()?->id;
        $filters = $request->validated();
        $query = $history->movements($event, $itemId, $filters);

        return new InventoryDataTableResource([
            'draw' => (int) ($filters['draw'] ?? 0),
            'total' => $history->movements($event, $itemId)->count(),
            'filtered' => (clone $query)->count(),
            'rows' => StockMovementResource::collection($history->ordered($query, $event)
                ->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 10)->get()),
        ]);
    }
}
