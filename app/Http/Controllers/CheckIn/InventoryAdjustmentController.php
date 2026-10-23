<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\AdjustInventoryRequest;
use App\Http\Requests\CheckIn\InventoryAdjustmentOptionsRequest;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\InventoryReceiverResource;
use App\Models\Event;
use App\Models\StockHolder;
use App\Queries\InventoryQuery;
use App\Queries\InventoryTargetsQuery;
use App\Services\InventoryMovementService;
use App\Services\StockMovementService;
use App\Support\EventContext;
use App\Support\SqlLike;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentController extends Controller
{
    public function index(InventoryAdjustmentOptionsRequest $request, EventContext $context, InventoryTargetsQuery $targets, StockMovementService $stock): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());
        if ($request->validated('direction') === 'from_location') {
            $query = $targets->receivers($event, search: ($request->validated('search') ?? ''));
        } else {
            $query = DB::table('people as p')->whereExists(fn ($holders) => $holders->selectRaw('1')->from('stock_holders as holder')
                ->joinSub($stock->balancesQuery($event->id), 'balance', 'balance.holder_id', '=', 'holder.id')
                ->where('holder.event_id', $event->id)->whereColumn('holder.person_id', 'p.id')->where('balance.balance', '>', 0))
                ->whereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", ['%'.SqlLike::escape(mb_strtolower(($request->validated('search') ?? ''))).'%'])
                ->select('p.id', 'p.name')->orderByRaw('LOWER(p.name)')->orderBy('p.id');
        }

        return InventoryReceiverResource::collection($query->paginate(25));
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
