<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryRequest;
use App\Http\Resources\EntitlementItemOptionResource;
use App\Http\Resources\InventoryDataTableResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockHolder;
use App\Queries\InventoryQuery;
use App\Queries\InventoryTargetsQuery;
use App\Support\EventContext;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(InventoryRequest $request, EventContext $context, InventoryQuery $query, InventoryTargetsQuery $targets): Response
    {
        $event = $context->requireCurrent($request->user());
        $holder = StockHolder::where('event_id', $event->id)->where('person_id', $request->user()->person_id)->first();
        $canManage = $request->user()->can('inventory.manage', $event);
        $canOperate = ! $event->isLocked() && $targets->canReceive($event, $request->user()->person_id);

        return Inertia::render('CheckIn/Inventory', [
            'view' => $request->routeIs('check-in.my-stock') ? 'mine' : 'holdings',
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'canManage' => $canManage,
            'ownItems' => $holder === null ? [] : $query->itemRows($event->id, $query->items($event->id, $holder->id)->get()),
            'canMove' => $canOperate,
            'canAdjust' => ! $event->isLocked() && in_array('inventory.manage', $targets->permissions($event, $request->user()), true),
            'canReceive' => $targets->canReceive($event, $request->user()->person_id),
            'ownPersonId' => $request->user()->person_id,
            'items' => ($canManage || ! $request->routeIs('check-in.my-stock')) ? EntitlementItemOptionResource::collection($query->itemOptions($event))->resolve()
                : ($holder === null ? [] : $query->itemRows($event->id, $query->items($event->id, $holder->id)->get())),
            'locations' => $event->locations()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(InventoryRequest $request, EventContext $context, InventoryQuery $query): InventoryDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $holder = StockHolder::where('event_id', $event->id)->where('person_id', $request->user()->person_id)->first();
        $filters = $request->validated();
        $history = $request->routeIs('check-in.inventory.movements');
        $rows = collect();
        $total = 0;
        if ($holder !== null) {
            $builder = $history ? $query->movements($holder) : $query->items($event->id, $holder->id);
            $total = (clone $builder)->count();
            $rows = $builder->offset($filters['start'] ?? 0)->limit($filters['length'] ?? 25)->get();
        }

        return new InventoryDataTableResource(['draw' => (int) ($filters['draw'] ?? 0), 'total' => $total, 'filtered' => $total,
            'rows' => $history ? StockMovementResource::collection($rows) : $query->itemRows($event->id, $rows)]);
    }
}
