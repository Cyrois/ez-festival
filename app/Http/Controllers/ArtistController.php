<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\CreateArtistRequest;
use App\Http\Requests\Artists\IndexArtistsRequest;
use App\Http\Requests\Artists\StoreArtistNoteRequest;
use App\Http\Requests\Artists\StoreArtistRequest;
use App\Http\Requests\Artists\UpdateArtistRequest;
use App\Http\Requests\Artists\ViewArtistRequest;
use App\Http\Resources\ArtistEngagementNoteResource;
use App\Http\Resources\ArtistEngagementResource;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Models\Organization;
use App\Repositories\ArtistRepository;
use App\Services\ArtistService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function __construct(
        private readonly ArtistRepository $artists,
        private readonly ArtistService $artistService,
    ) {}

    public function index(IndexArtistsRequest $request): Response
    {
        $organization = $request->user()->primaryOrganization();
        $event = $request->user()->effectiveEvent($organization);
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $labelIds = $filters['labels'] ?? [];

        $engagements = $this->artists->paginateEngagements($organization, $event, $search, $labelIds);

        return Inertia::render('Artists/Index', [
            'engagements' => ArtistEngagementResource::collection($engagements),
            'labels' => $this->artists->labelsFor($organization),
            'filters' => ['search' => $search, 'labels' => array_map('intval', $labelIds)],
            'event' => $event?->only('id', 'name', 'locked'),
        ]);
    }

    public function create(CreateArtistRequest $request): Response
    {
        $organization = $request->user()->primaryOrganization();
        $event = $request->user()->effectiveEvent($organization);
        abort_if($event === null, 404);
        $event->ensureWritable($organization);

        return Inertia::render('Artists/Create', [
            'event' => $event->only('id', 'name'),
            'types' => $organization->artistTypes()->orderBy('name')->get(['id', 'name']),
            'labels' => $organization->artistLabels()->orderBy('name')->get(['id', 'name', 'color']),
            'statuses' => ArtistEngagement::STATUSES,
            'labelColors' => ArtistLabel::COLORS,
        ]);
    }

    public function store(StoreArtistRequest $request, Event $event): RedirectResponse
    {
        $organization = $request->user()->primaryOrganization();
        $effectiveEvent = $request->user()->effectiveEvent($organization);
        abort_unless($effectiveEvent?->is($event), 404);
        $event->ensureWritable($organization);
        $this->artistService->addToEvent($organization, $event, $request->validated());

        return redirect()->route('artists.index')
            ->with('success', __('artists.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function view(ViewArtistRequest $request, ArtistEngagement $engagement): Response
    {
        [$organization, $event] = $this->resolveWritableContext($request, $engagement, writable: false);

        $engagement->load(['artist', 'labels' => fn ($query) => $query->orderBy('name'), 'artistType']);

        $notes = $engagement->notes()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return Inertia::render('Artists/View', [
            'engagement' => (new ArtistEngagementResource($engagement))->resolve(),
            'notes' => ArtistEngagementNoteResource::collection($notes)->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'types' => $organization->artistTypes()->orderBy('name')->get(['id', 'name']),
            'labels' => $organization->artistLabels()->orderBy('name')->get(['id', 'name', 'color']),
            'statuses' => ArtistEngagement::STATUSES,
            'labelColors' => ArtistLabel::COLORS,
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function update(UpdateArtistRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        [$organization] = $this->resolveWritableContext($request, $engagement, writable: true);
        $this->artistService->updateEngagement($organization, $engagement, $request->validated());

        return redirect()->route('artists.view', $engagement)
            ->with('success', __('artists.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function storeNote(StoreArtistNoteRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        [$organization] = $this->resolveWritableContext($request, $engagement, writable: true);
        $this->artistService->addNote(
            $organization,
            $engagement,
            $request->user(),
            $request->validated('body'),
        );

        return redirect()->route('artists.view', $engagement)
            ->with('success', __('artists.toast.note_posted'))
            ->with('success_title', __('toast.saved_title'));
    }

    /**
     * @return array{0: Organization, 1: Event}
     */
    private function resolveWritableContext(
        ViewArtistRequest|UpdateArtistRequest|StoreArtistNoteRequest $request,
        ArtistEngagement $engagement,
        bool $writable,
    ): array {
        $organization = $request->user()->primaryOrganization();
        $effectiveEvent = $request->user()->effectiveEvent($organization);
        abort_if($effectiveEvent === null, 404);

        $engagement->loadMissing(['artist', 'event']);

        abort_unless(
            (int) $engagement->event_id === (int) $effectiveEvent->id
            && (int) $engagement->artist->organization_id === (int) $organization->id,
            404,
        );

        $event = $engagement->event;
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);

        if ($writable) {
            $event->ensureWritable($organization);
        }

        return [$organization, $event];
    }
}
