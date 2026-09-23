<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\ConsumeArtistEntitlementRequest;
use App\Http\Requests\Artists\IndexArtistCheckInRequest;
use App\Http\Requests\Artists\ViewArtistCheckInRequest;
use App\Http\Resources\ArtistCheckInListResource;
use App\Http\Resources\ArtistCheckInShowResource;
use App\Models\ArtistEngagement;
use App\Models\ExpectedEntitlement;
use App\Services\EntitlementConsumeService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArtistCheckInController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(IndexArtistCheckInRequest $request): Response
    {
        $event = $this->eventContext->requireCurrent($request->user());
        $held = 'exists (select 1 from artist_engagement_people aep where aep.artist_engagement_id = pass_assignments.artist_engagement_id and aep.person_id = pass_assignments.person_id)';
        $expected = "(select count(*) from expected_entitlements ee join pass_assignments on pass_assignments.id = ee.pass_assignment_id where pass_assignments.artist_engagement_id = artist_engagements.id and pass_assignments.person_id is not null and {$held})";
        $issued = "(select count(*) from issued_entitlements ie join expected_entitlements ee on ee.id = ie.expected_entitlement_id join pass_assignments on pass_assignments.id = ee.pass_assignment_id where pass_assignments.artist_engagement_id = artist_engagements.id and pass_assignments.person_id is not null and {$held})";

        $engagements = ArtistEngagement::query()
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->select('artist_engagements.*')
            ->selectRaw("{$expected} as expected_count, {$issued} as issued_count")
            ->with(['artist', 'people' => fn ($query) => $query->orderByDesc('artist_engagement_people.is_primary')->orderBy('people.name')])
            ->orderByRaw('(select lower(name) from artists where artists.id = artist_engagements.artist_id)')
            ->get();

        return Inertia::render('Artists/CheckIn', [
            'engagements' => ArtistCheckInListResource::collection($engagements)->resolve(),
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

        return Inertia::render('Artists/CheckInShow', [
            'engagement' => (new ArtistCheckInShowResource($engagement))->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'canWrite' => ! $event->isLocked(),
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
