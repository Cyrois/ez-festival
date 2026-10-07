<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\IndexIssuedEntitlementRequest;
use App\Http\Resources\IssuedEntitlementResource;
use App\Http\Responses\Credentials\IssuedEntitlementDataTableResponse;
use App\Models\EntitlementItem;
use App\Support\EventContext;
use App\Support\SqlLike;

class IssuedEntitlementController extends Controller
{
    public function index(IndexIssuedEntitlementRequest $request, EntitlementItem $entitlementItem): IssuedEntitlementDataTableResponse
    {
        $event = app(EventContext::class)->requireCurrent($request->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);
        $data = $request->validated();
        $query = $entitlementItem->issuedEntitlements();
        $total = $query->count();
        $search = trim($data['search']['value'] ?? '');
        if ($search !== '') {
            $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
            $query->where(fn ($query) => $query->whereRaw("lower(coalesce(code, '')) like ? escape '!'", [$pattern])
                ->orWhereHas('expectedEntitlement.passAssignment.passType', fn ($query) => $query->whereRaw("lower(name) like ? escape '!'", [$pattern]))
                ->orWhereHas('issuedBy', fn ($query) => $query->whereRaw("lower(name) like ? escape '!'", [$pattern])));
        }
        $filtered = $query->count();
        $rows = $query->with(['expectedEntitlement.passAssignment.passType', 'issuedBy'])
            ->latest('issued_at')->latest('id')->offset((int) ($data['start'] ?? 0))->limit((int) ($data['length'] ?? 25))->get();

        return new IssuedEntitlementDataTableResponse((int) ($data['draw'] ?? 0), $total, $filtered, IssuedEntitlementResource::collection($rows)->resolve());
    }
}
