<?php

namespace App\Services;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Support\EventContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckInPageService
{
    public function __construct(private readonly EventContext $eventContext) {}

    /** Load the shared check-in page; the domain resource controls personal-info disclosure. */
    public function show(ViewCheckInRequest $request, Model $engagement, JsonResource $resource, string $relation): Response
    {
        $event = $this->eventContext->requireCurrent($request->user());
        abort_unless($engagement->event_id === $event->id && $engagement->status === 'confirmed', 404);
        $engagement->load([
            $relation,
            'people' => fn ($query) => $query->orderBy('people.name'),
            'passAssignments.passType.labels',
            // The check-in resource needs location balances, not ledger history.
            'passAssignments.expectedEntitlements.entitlementItem.adjustments' => fn ($query) => $query
                ->select('entitlement_item_id', 'location_id')
                ->selectRaw('SUM(delta) as balance')
                ->whereNotNull('location_id')
                ->groupBy('entitlement_item_id', 'location_id')
                ->havingRaw('SUM(delta) > 0')
                ->with('location'),
            'passAssignments.expectedEntitlements.issuedEntitlement.location',
        ]);
        $personId = $request->integer('person');

        return Inertia::render('CheckIn/Show', [
            'engagement' => $resource->resolve($request),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'canWrite' => ! $event->isLocked() && Gate::allows('checkin.edit', $event),
            'selectedPersonId' => $engagement->people->contains('id', $personId) ? $personId : null,
        ]);
    }
}
