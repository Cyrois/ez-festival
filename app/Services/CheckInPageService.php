<?php

namespace App\Services;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Http\Resources\MemberShiftResource;
use App\Models\TeamEngagement;
use App\Repositories\ShiftAssignmentRepository;
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
        $isTeam = $engagement instanceof TeamEngagement;
        abort_unless($engagement->event_id === $event->id && $engagement->status === ($isTeam ? 'hired' : 'confirmed'), 404);
        $engagement->load([
            $relation,
            ...($isTeam ? ['person', 'group', 'role'] : ['people' => fn ($query) => $query->orderBy('people.name')]),
            'passAssignments' => fn ($query) => $query
                ->whereHas('passType', fn ($passes) => $passes->where('event_id', $event->id))
                ->withCount([
                    'expectedEntitlements' => fn ($expected) => $expected->whereHas('entitlementItem', fn ($items) => $items->where('event_id', $event->id)),
                    'expectedEntitlements as issued_count' => fn ($expected) => $expected
                        ->whereHas('entitlementItem', fn ($items) => $items->where('event_id', $event->id))->whereHas('issuedEntitlement'),
                ]),
            'passAssignments.passType.labels',
        ]);
        $people = $isTeam ? collect([$engagement->person]) : $engagement->people;
        $personId = $request->integer('person');

        $canViewShifts = $isTeam && Gate::allows('scheduling.view', $event);

        return Inertia::render($isTeam ? 'CheckIn/Team' : 'CheckIn/Show', [
            'engagement' => $resource->resolve($request),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'canWrite' => ! $event->isLocked() && Gate::allows('checkin.edit', $event),
            'selectedPersonId' => $people->contains('id', $personId) ? $personId : null,
            ...($isTeam ? [
                'memberUrl' => Gate::allows('team.view', $event) ? route('team.members.show', $engagement) : null,
                'canViewShifts' => $canViewShifts,
                ...($canViewShifts ? [
                    'checkInShifts' => MemberShiftResource::collection(
                        app(ShiftAssignmentRepository::class)->recentAndUpcomingForMember($event, $engagement),
                    )->resolve($request),
                ] : []),
            ] : []),
        ]);
    }
}
