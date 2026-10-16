<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ManageInventoryRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\InventoryReceiverResource;
use App\Queries\InventoryTargetsQuery;
use App\Services\StockMovementService;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryOptionsController extends Controller
{
    public function index(ManageInventoryRequest $request, EventContext $context, InventoryTargetsQuery $targets): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());
        $filters = $request->validated();

        return InventoryReceiverResource::collection(
            $targets->receivers($event, search: $filters['search'] ?? '')->paginate(25));
    }

    public function show(ManageInventoryRequest $request, EventContext $context, StockMovementService $stock): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $filters = $request->validated();
        $rows = $stock->locationBalancesQuery($event->id)->where('stock_balances.balance', '>', 0)
            ->when(! empty($filters['location_id']), fn ($q) => $q->where('sh.location_id', $filters['location_id']))
            ->get()->map(fn ($row) => ['location_id' => (int) $row->location_id,
                'entitlement_item_id' => (int) $row->entitlement_item_id, 'balance' => (int) $row->balance]);

        return new InventoryDataTableResource(['draw' => 0, 'total' => $rows->count(), 'filtered' => $rows->count(), 'rows' => $rows]);
    }
}
