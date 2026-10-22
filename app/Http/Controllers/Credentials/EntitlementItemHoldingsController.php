<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryHistoryRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Models\EntitlementItem;
use App\Queries\InventoryQuery;

class EntitlementItemHoldingsController extends Controller
{
    public function index(InventoryHistoryRequest $request, InventoryQuery $inventory, EntitlementItem $entitlementItem): InventoryDataTableResource
    {
        $item = $request->item();
        $filters = $request->validated();
        $query = $inventory->peopleHoldingItem($item);
        $total = (clone $query)->count();

        return new InventoryDataTableResource([
            'draw' => (int) ($filters['draw'] ?? 0), 'total' => $total, 'filtered' => $total,
            'rows' => $inventory->peopleHoldingItemRows($request->event(), $query
                ->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 10)->get()),
        ]);
    }
}
