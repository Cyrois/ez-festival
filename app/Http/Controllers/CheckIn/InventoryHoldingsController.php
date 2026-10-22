<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ManageInventoryRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Models\StockHolder;
use App\Queries\InventoryQuery;
use App\Support\EventContext;

class InventoryHoldingsController extends Controller
{
    public function index(ManageInventoryRequest $request, EventContext $context, InventoryQuery $query): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $filters = $request->validated();
        $total = (clone $query->holdings($event, ['holder_type' => $filters['holder_type'] ?? null]))->getCountForPagination();
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
        $builder = $query->items($holder->event_id, $holder->id);
        $total = (clone $builder)->count();
        $rows = $builder->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 25)->get();

        return new InventoryDataTableResource(['draw' => (int) ($filters['draw'] ?? 0), 'total' => $total, 'filtered' => $total,
            'rows' => $query->itemRows($holder->event_id, $rows)]);
    }
}
