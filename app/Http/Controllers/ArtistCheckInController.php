<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\ConsumeArtistEntitlementRequest;
use App\Http\Requests\Artists\IndexArtistCheckInRequest;
use App\Http\Requests\Artists\ViewArtistCheckInRequest;
use App\Http\Resources\ArtistCheckInShowResource;
use App\Http\Resources\CheckInPersonResource;
use App\Models\ArtistEngagement;
use App\Models\ExpectedEntitlement;
use App\Queries\ArtistCheckInPeopleQuery;
use App\Services\EntitlementConsumeService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ArtistCheckInController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly ArtistCheckInPeopleQuery $artistCheckInPeople,
    ) {}

    public function index(IndexArtistCheckInRequest $request): Response
    {
        $event = $this->eventContext->requireCurrent($request->user());
        $filters = $request->validated();
        $type = $filters['type'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $passId = isset($filters['pass']) ? (int) $filters['pass'] : null;
        $search = trim($filters['search'] ?? '');
        $canEdit = Gate::allows('manage-artists');

        $people = in_array($type, ['all', 'artist'], true)
            ? $this->artistCheckInPeople->paginate($event->id, $passId, $search, $status, $canEdit)
            : $this->artistCheckInPeople->empty();

        return Inertia::render('CheckIn/Index', [
            'people' => CheckInPersonResource::collection($people),
            'passes' => $event->passTypes()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'type' => $type,
                'pass' => $passId,
                'status' => $status,
                'search' => $search,
            ],
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
        ]);
    }

    public function show(ViewArtistCheckInRequest $request, ArtistEngagement $engagement): Response
    {
        $event = $this->eventContext->requireCurrent($request->user());
        abort_unless($engagement->event_id === $event->id && $engagement->status === 'confirmed', 404);

        $engagement->load([
            'artist',
            'people' => fn ($query) => $query->orderBy('people.name'),
            'passAssignments.passType.labels',
            'passAssignments.expectedEntitlements.entitlementItem.adjustments.location',
            'passAssignments.expectedEntitlements.issuedEntitlement.location',
        ]);
        $requestedPersonId = $request->integer('person');

        return Inertia::render('Artists/CheckInShow', [
            'engagement' => (new ArtistCheckInShowResource($engagement))->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'canWrite' => ! $event->isLocked(),
            'selectedPersonId' => $engagement->people->contains('id', $requestedPersonId)
                ? $requestedPersonId
                : null,
        ]);
    }

    public function store(
        ConsumeArtistEntitlementRequest $request,
        ExpectedEntitlement $expectedEntitlement,
        EntitlementConsumeService $consume,
    ): RedirectResponse {
        $expectedEntitlement->loadMissing('passAssignment.artistEngagement.people');
        $assignment = $expectedEntitlement->passAssignment;
        $engagement = $assignment->artistEngagement;
        abort_unless($engagement !== null && $engagement->status === 'confirmed' && $assignment->person_id !== null, 404);
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        abort_unless($engagement->people->contains('id', $assignment->person_id), 404);
        $data = $request->validated();
        $consume->consume($expectedEntitlement, $request->user(), (int) $data['location_id'], $data['code'] ?? null);

        return back()->with('success', __('artists.check_in.toast.consumed'));
    }
}
