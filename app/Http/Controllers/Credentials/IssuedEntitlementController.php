<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\IndexIssuedEntitlementsRequest;
use App\Http\Resources\IssuedEntitlementDataTableResource;
use App\Models\EntitlementItem;
use App\Services\EntitlementItemService;
use App\Support\EventContext;

class IssuedEntitlementController extends Controller
{
    public function index(IndexIssuedEntitlementsRequest $request, EntitlementItem $entitlementItem, EventContext $context, EntitlementItemService $items): IssuedEntitlementDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);

        $data = $request->validated();

        return new IssuedEntitlementDataTableResource([
            'draw' => (int) $data['draw'],
            ...$items->issuedLog($entitlementItem, (int) $data['start'], (int) $data['length']),
        ]);
    }
}
