<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryRequest;
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
            'ownItems' => $holder === null ? [] : $query->items($event->id, $holder->id)->get()->map(fn ($item) => [
                'id' => (int) $item->id, 'name' => $item->name, 'balance' => (int) $item->balance,
            ]),
            'canMove' => $canOperate,
            'canPull' => $canManage && $canOperate,
            'canTakeBack' => $canManage && $canOperate,
            'ownPersonId' => $request->user()->person_id,
            'items' => $canManage ? $event->entitlementItems()->orderBy('name')->get(['id', 'name'])
                : ($holder === null ? [] : $query->items($event->id, $holder->id)->get(['i.id', 'i.name'])),
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
            'rows' => $history ? StockMovementResource::collection($rows) : $rows->map(fn ($row) => [
                'id' => (int) $row->id, 'name' => $row->name, 'balance' => (int) $row->balance,
            ])]);
    }
}
