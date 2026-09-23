<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\ConsumeArtistEntitlementRequest;
use App\Models\ArtistEngagement;
use App\Models\ExpectedEntitlement;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ArtistCheckInController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-artists');
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
            ->get()
            ->map(function (ArtistEngagement $engagement): array {
                $expected = (int) $engagement->expected_count;
                $issued = (int) $engagement->issued_count;

                return [
                    'id' => $engagement->id,
                    'name' => $engagement->artist->name,
                    'contact' => $engagement->people->first()?->only('name', 'email'),
                    'expected' => $expected,
                    'issued' => $issued,
                    'check_in_status' => $this->status($issued, $expected),
                ];
            });

        return Inertia::render('Artists/CheckIn', [
            'engagements' => $engagements,
            'event' => $event->only('id', 'name', 'locked'),
        ]);
    }

    public function show(Request $request, ArtistEngagement $engagement, EntitlementItemService $items): Response
    {
        Gate::authorize('view-artists');
        $event = $this->eventContext->requireCurrent($request->user());
        abort_unless($engagement->event_id === $event->id && $engagement->status === 'confirmed', 404);

        $engagement->load([
            'artist',
            'people' => fn ($query) => $query->orderBy('people.name'),
            'passAssignments.passType',
            'passAssignments.expectedEntitlements.entitlementItem.adjustments.location',
            'passAssignments.expectedEntitlements.issuedEntitlement.location',
        ]);

        $assignments = $engagement->passAssignments
            ->whereNotNull('person_id')
            ->groupBy('person_id');

        $people = $engagement->people->map(function ($person) use ($assignments, $items): array {
            $held = $assignments->get($person->id, collect());
            $entitlements = $held->flatMap(fn ($assignment) => $assignment->expectedEntitlements->map(function ($expected) use ($assignment, $items): array {
                $issued = $expected->issuedEntitlement;
                $balances = $items->balanceByLocation($expected->entitlementItem);
                $locations = $expected->entitlementItem->adjustments
                    ->pluck('location')
                    ->filter()
                    ->unique('id')
                    ->filter(fn ($location) => ($balances[$location->id] ?? 0) > 0)
                    ->sortBy('name')
                    ->values()
                    ->map(fn ($location): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'in_stock' => $balances[$location->id],
                    ]);

                return [
                    'id' => $expected->id,
                    'name' => $expected->entitlementItem->name,
                    'source' => $assignment->passType->name,
                    'status' => $issued ? 'issued' : 'pending',
                    'locations' => $locations,
                    'issued' => $issued ? [
                        'location' => $issued->location?->name,
                        'code' => $issued->code,
                        'issued_at' => $issued->issued_at,
                    ] : null,
                ];
            }))->sortBy('name')->values();

            return [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'is_primary' => (bool) $person->pivot->is_primary,
                'passes' => $held->pluck('passType.name')->unique()->values(),
                'issued' => $entitlements->where('status', 'issued')->count(),
                'expected' => $entitlements->count(),
                'entitlements' => $entitlements,
            ];
        })->values();

        return Inertia::render('Artists/CheckInShow', [
            'engagement' => [
                'id' => $engagement->id,
                'name' => $engagement->artist->name,
                'people' => $people,
            ],
            'event' => $event->only('id', 'name', 'locked'),
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

    private function status(int $issued, int $expected): string
    {
        return match (true) {
            $expected === 0, $issued >= $expected => 'complete',
            $issued === 0 => 'not_started',
            default => 'partial',
        };
    }
}
