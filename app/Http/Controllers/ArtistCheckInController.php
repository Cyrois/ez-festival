<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\ConsumeArtistEntitlementRequest;
use App\Http\Requests\Artists\IndexArtistCheckInRequest;
use App\Http\Requests\Artists\ViewArtistCheckInRequest;
use App\Http\Resources\ArtistCheckInShowResource;
use App\Http\Resources\CheckInPersonResource;
use App\Models\ArtistEngagement;
use App\Models\ExpectedEntitlement;
use App\Services\EntitlementConsumeService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ArtistCheckInController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(IndexArtistCheckInRequest $request): Response
    {
        $event = $this->eventContext->requireCurrent($request->user());
        $filters = $request->validated();
        $type = $filters['type'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $passId = isset($filters['pass']) ? (int) $filters['pass'] : null;
        $search = trim($filters['search'] ?? '');

        $rows = in_array($type, ['all', 'artist'], true)
            ? $this->artistRows($event->id, $passId)
            : collect();

        if ($search !== '') {
            $needle = Str::lower($search);
            $rows = $rows->filter(fn (array $row): bool => Str::contains(
                Str::lower(implode(' ', $row['searchable'])),
                $needle,
            ));
        }

        if ($status !== 'all') {
            $rows = $rows->where('check_in_status', $status);
        }

        return Inertia::render('CheckIn/Index', [
            'people' => CheckInPersonResource::collection($rows->values())->resolve(),
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

    /** @return Collection<int, array<string, mixed>> */
    private function artistRows(int $eventId, ?int $passId): Collection
    {
        $engagements = ArtistEngagement::query()
            ->where('event_id', $eventId)
            ->where('status', 'confirmed')
            ->whereHas('passAssignments', fn ($query) => $query->whereNotNull('person_id'))
            ->with([
                'artist:id,name',
                'people' => fn ($query) => $query->orderBy('people.name'),
                'passAssignments' => fn ($query) => $query
                    ->whereNotNull('person_id')
                    ->with(['passType:id,name', 'expectedEntitlements.issuedEntitlement']),
            ])
            ->get();

        return $engagements->flatMap(function (ArtistEngagement $engagement) use ($passId): Collection {
            return $engagement->people->map(function ($person) use ($engagement, $passId): ?array {
                $assignments = $engagement->passAssignments
                    ->where('person_id', $person->id)
                    ->when($passId, fn (Collection $rows) => $rows->where('pass_type_id', $passId));

                if ($assignments->isEmpty()) {
                    return null;
                }

                $entitlements = $assignments->flatMap->expectedEntitlements;
                $expected = $entitlements->count();
                $issued = $entitlements->filter->issuedEntitlement->count();
                $checkInStatus = match (true) {
                    $expected === 0, $issued >= $expected => 'complete',
                    $issued === 0 => 'not_started',
                    default => 'partial',
                };
                $passes = $assignments->pluck('passType.name')->filter()->unique()->values();
                $codes = $entitlements->pluck('issuedEntitlement.code')->filter()->values();

                return [
                    'person_id' => $person->id,
                    'engagement_id' => $engagement->id,
                    'name' => $person->name,
                    'subtitle' => $person->email,
                    'type' => 'artist',
                    'context' => $engagement->artist->name,
                    'pass_name' => $passes->join(', '),
                    'issued' => $issued,
                    'expected' => $expected,
                    'check_in_status' => $checkInStatus,
                    'can_edit' => Gate::allows('manage-artists'),
                    'searchable' => collect([$person->name, $person->email, $engagement->artist->name])
                        ->merge($passes)
                        ->merge($codes)
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })->filter();
        })->sortBy(fn (array $row) => Str::lower($row['name']))->values();
    }
}
