<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ManageInventoryRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockHolder;
use App\Queries\InventoryQuery;
use App\Support\EventContext;

class InventoryHoldingsController extends Controller
{
    public function index(ManageInventoryRequest $request, EventContext $context, InventoryQuery $query): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $filters = $request->validated();
        $total = (clone $query->holdings($event))->getCountForPagination();
        $builder = $query->holdings($event, $filters);
        $filtered = (clone $builder)->getCountForPagination();
        $rows = $builder->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 25)->get();

        return new InventoryDataTableResource(['draw' => (int) ($filters['draw'] ?? 0), 'total' => $total,
            'filtered' => $filtered, 'rows' => $query->holdingRows($event, $rows->all())]);
    }

    public function show(ManageInventoryRequest $request, StockHolder $holder, EventContext $context, InventoryQuery $query): InventoryDataTableResource
    {
        $context->requireCurrentEvent($request->user(), $holder->event);
        $filters = $request->validated();
        $history = $request->routeIs('check-in.inventory.holders.movements');
        $builder = $history ? $query->movements($holder) : $query->items($holder->event_id, $holder->id);
        $total = (clone $builder)->count();
        $rows = $builder->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 25)->get();

        return new InventoryDataTableResource(['draw' => (int) ($filters['draw'] ?? 0), 'total' => $total, 'filtered' => $total,
            'rows' => $history ? StockMovementResource::collection($rows) : $rows->map(fn ($row) => [
                'id' => (int) $row->id, 'name' => $row->name, 'balance' => (int) $row->balance,
            ])]);
    }
}
