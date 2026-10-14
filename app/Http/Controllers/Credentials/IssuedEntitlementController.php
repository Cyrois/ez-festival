<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\IndexIssuedEntitlementsRequest;
use App\Http\Resources\IssuedEntitlementDataTableResource;
use App\Models\EntitlementItem;
use App\Support\EventContext;

class IssuedEntitlementController extends Controller
{
    public function index(IndexIssuedEntitlementsRequest $request, EntitlementItem $entitlementItem, EventContext $context): IssuedEntitlementDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);

        $data = $request->validated();
        $query = $entitlementItem->issuedEntitlements();
        $total = $query->count();

        return new IssuedEntitlementDataTableResource([
            'draw' => (int) $data['draw'],
            'total' => $total,
            'issues' => $query->with(['expectedEntitlement.passAssignment.passType:id,name', 'issuedBy:id,name'])
                ->latest('issued_at')->orderByDesc('id')
                ->offset((int) $data['start'])->limit((int) $data['length'])->get(),
        ]);
    }
}
