<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\CreateArtistRequest;
use App\Http\Requests\Artists\IndexArtistsRequest;
use App\Http\Requests\Artists\StoreArtistRequest;
use App\Http\Resources\ArtistEngagementResource;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
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
}
