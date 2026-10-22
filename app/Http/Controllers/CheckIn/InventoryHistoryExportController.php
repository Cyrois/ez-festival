<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryHistoryRequest;
use App\Http\Responses\InventoryHistoryCsvResponse;
use App\Queries\InventoryHistoryQuery;

class InventoryHistoryExportController extends Controller
{
    public function index(InventoryHistoryRequest $request, InventoryHistoryQuery $history): InventoryHistoryCsvResponse
    {
        $event = $request->event();
        $query = $history->movements($event, filters: $request->validated());
        // Bound the immutable ledger so moves recorded during export cannot shift its pages.
        $lastId = (clone $query)->max('id') ?? 0;

        return new InventoryHistoryCsvResponse($event, $history->ordered($query->where('id', '<=', $lastId), $event));
    }
}
