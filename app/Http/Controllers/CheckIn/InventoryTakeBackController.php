<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\HoldingsPersonRequest;
use App\Http\Requests\CheckIn\InventoryBatchRequest;
use App\Http\Resources\InventoryReceiverResource;
use App\Models\Event;
use App\Queries\InventoryTargetsQuery;
use App\Services\InventoryHoldingsAccess;
use App\Services\InventoryMovementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryTakeBackController extends Controller
{
    public function index(HoldingsPersonRequest $request, EventContext $context, InventoryTargetsQuery $targets, InventoryHoldingsAccess $access): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());
        $manager = $targets->allows($event, $request->user(), 'inventory.manage', requireHired: false);
        $access->authorizePerson($event, $request->user(), $request->validated(), $request->integer('person_id'), $manager);
        $query = $targets->receivers($event, $request->integer('person_id'), ($request->validated('search') ?? ''));
        if (! $targets->allows($event, $request->user(), 'inventory.manage')) {
            $query->whereIn('p.id', $access->roster($event, $request->validated())->select('member.person_id'));
        }

        return InventoryReceiverResource::collection($query->paginate(25));
    }

    public function store(InventoryBatchRequest $request, Event $event, EventContext $context, InventoryMovementService $movements): RedirectResponse
    {
        $context->requireCurrentEvent($request->user(), $event, writable: true);
        $movements->takeBackItems($event, $request->user(), $request->validated());

        return back();
    }
}
