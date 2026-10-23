<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ManageInventoryRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Queries\InventoryHoldingsQuery;
use App\Support\EventContext;

class InventoryLocationsController extends Controller
{
    public function index(ManageInventoryRequest $request, EventContext $context, InventoryHoldingsQuery $query): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $builder = $query->locations($event);
        $total = (clone $builder)->count();
        $rows = $builder->offset($request->validated('start', 0))->limit($request->validated('length', 25))->get();

        return new InventoryDataTableResource(['draw' => (int) $request->validated('draw', 0), 'total' => $total,
            'filtered' => $total, 'rows' => $query->rows($event, $rows, false)]);
    }
}
