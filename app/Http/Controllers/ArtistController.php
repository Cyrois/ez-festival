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
use App\Models\ArtistType;
use App\Models\Event;
use App\Repositories\ArtistRepository;
use App\Services\ArtistService;
use App\Support\EventContext;
use App\Support\LabelColors;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function __construct(
        private readonly ArtistRepository $artists,
        private readonly ArtistService $artistService,
        private readonly EventContext $eventContext,
    ) {}

    public function index(IndexArtistsRequest $request): Response
    {
        $event = $request->user()->effectiveEvent();
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $labelIds = $filters['labels'] ?? [];

        $engagements = $this->artists->paginateEngagements($event, $search, $labelIds);

        return Inertia::render('Artists/Index', [
            'engagements' => ArtistEngagementResource::collection($engagements),
            'labels' => $this->artists->labelsFor(),
            'filters' => ['search' => $search, 'labels' => array_map('intval', $labelIds)],
            'event' => $event?->only('id', 'name', 'locked'),
        ]);
    }

    public function checkIn(): Response
    {
        return Inertia::render('Artists/CheckIn');
    }

    public function create(CreateArtistRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Artists/Create', [
            'event' => $event->only('id', 'name'),
            'types' => ArtistType::query()->orderBy('name')->get(['id', 'name']),
            'labels' => ArtistLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'statuses' => ArtistEngagement::STATUSES,
            'labelColors' => LabelColors::ALL,
        ]);
    }

    public function store(StoreArtistRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->artistService->addToEvent($event, $request->validated());

        return redirect()->route('artists.index')
            ->with('success', __('artists.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function view(ViewArtistRequest $request, ArtistEngagement $engagement): Response
    {
        $event = $this->resolveEventContext($request, $engagement, writable: false);

        $engagement->load([
            'artist',
            'labels' => fn ($query) => $query->orderBy('name'),
            'artistType',
            'people' => fn ($query) => $query->orderByDesc('artist_engagement_people.is_primary')->orderBy('people.name'),
            'passAssignments.passType',
            'passAssignments.person',
        ]);

        $notes = $engagement->notes()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return Inertia::render('Artists/View', [
            'engagement' => (new ArtistEngagementResource($engagement))->resolve(),
            'notes' => ArtistEngagementNoteResource::collection($notes)->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'types' => ArtistType::query()->orderBy('name')->get(['id', 'name']),
            'labels' => ArtistLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'statuses' => ArtistEngagement::STATUSES,
            'labelColors' => LabelColors::ALL,
            'passes' => $event->passTypes()->withCount('assignments')->orderBy('name')->get(['id', 'name', 'max_assignments']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function update(UpdateArtistRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        $this->resolveEventContext($request, $engagement, writable: true);
        $this->artistService->updateEngagement($engagement, $request->validated());

        return redirect()->route('artists.view', $engagement)
            ->with('success', __('artists.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function storeNote(StoreArtistNoteRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        $this->resolveEventContext($request, $engagement, writable: true);
        $this->artistService->addNote(
            $engagement,
            $request->user(),
            $request->validated('body'),
        );

        return redirect()->route('artists.view', $engagement)
            ->with('success', __('artists.toast.note_posted'))
            ->with('success_title', __('toast.saved_title'));
    }

    private function resolveEventContext(
        ViewArtistRequest|UpdateArtistRequest|StoreArtistNoteRequest $request,
        ArtistEngagement $engagement,
        bool $writable,
    ): Event {
        $engagement->loadMissing('event');

        return $this->eventContext->requireCurrentEvent(
            $request->user(),
            $engagement->event,
            writable: $writable,
        );
    }
}
