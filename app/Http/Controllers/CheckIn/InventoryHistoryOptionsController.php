<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryHistoryRequest;
use App\Http\Resources\InventoryHistoryOptionResource;
use App\Models\EntitlementItem;
use App\Queries\InventoryHistoryQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryHistoryOptionsController extends Controller
{
    public function index(InventoryHistoryRequest $request, InventoryHistoryQuery $history, ?EntitlementItem $entitlementItem = null): AnonymousResourceCollection
    {
        return InventoryHistoryOptionResource::collection($history->options(
            $request->event(), $request->item()?->id, $request->validated('option') ?? 'holder', $request->validated('search') ?? '',
        )->paginate(20));
    }
}
