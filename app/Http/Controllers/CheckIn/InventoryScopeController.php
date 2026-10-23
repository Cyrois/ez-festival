<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryScopeRequest;
use App\Http\Resources\InventoryScopeOptionResource;
use App\Queries\InventoryScopeQuery;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryScopeController extends Controller
{
    public function index(InventoryScopeRequest $request, EventContext $context, InventoryScopeQuery $query): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());

        return InventoryScopeOptionResource::collection($query->options($event, $request->user(), $request->validated())->paginate(25));
    }
}
