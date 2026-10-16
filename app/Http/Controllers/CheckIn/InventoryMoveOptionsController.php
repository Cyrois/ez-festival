<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryRequest;
use App\Http\Resources\InventoryReceiverResource;
use App\Queries\InventoryTargetsQuery;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryMoveOptionsController extends Controller
{
    public function index(InventoryRequest $request, EventContext $context, InventoryTargetsQuery $targets): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());

        return InventoryReceiverResource::collection($targets->moveReceivers($event, $request->user(), $request->validated('search') ?? '')->paginate(25));
    }
}
