<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\HoldingsPersonRequest;
use App\Http\Requests\CheckIn\HoldingsRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Models\StockHolder;
use App\Queries\InventoryHoldingsQuery;
use App\Queries\InventoryQuery;
use App\Queries\InventoryTargetsQuery;
use App\Services\InventoryHoldingsAccess;
use App\Support\EventContext;

class InventoryHoldingsController extends Controller
{
    public function index(HoldingsRequest $request, EventContext $context, InventoryHoldingsAccess $access, InventoryHoldingsQuery $query, InventoryTargetsQuery $targets): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $filters = $request->validated();
        $access->authorizeScope($event, $request->user(), $filters, $targets->allows($event, $request->user(), 'inventory.manage', requireHired: false));
        $builder = $query->people($event, $filters);
        $total = (clone $builder)->count();
        $rows = $builder->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 25)->get();

        return new InventoryDataTableResource(['draw' => (int) ($filters['draw'] ?? 0), 'total' => $total,
            'filtered' => $total, 'rows' => $query->rows($event, $rows), 'canAct' => $access->canAct($event, $request->user(), $filters)]);
    }

    public function show(HoldingsPersonRequest $request, EventContext $context, InventoryHoldingsAccess $access, InventoryQuery $query, InventoryTargetsQuery $targets): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $access->authorizePerson($event, $request->user(), $request->validated(), $request->integer('person_id'), $targets->allows($event, $request->user(), 'inventory.manage', requireHired: false));
        $holder = StockHolder::where('event_id', $event->id)->where('person_id', $request->integer('person_id'))->first();
        $rows = $holder === null ? collect() : $query->itemRows($event->id, $query->items($event->id, $holder->id)->get());

        return new InventoryDataTableResource(['draw' => 0, 'total' => $rows->count(), 'filtered' => $rows->count(), 'rows' => $rows]);
    }
}
