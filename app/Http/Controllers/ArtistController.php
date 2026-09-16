<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\CreateArtistRequest;
use App\Http\Requests\Artists\IndexArtistsRequest;
use App\Http\Requests\Artists\StoreArtistRequest;
use App\Http\Resources\ArtistEngagementResource;
use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function index(IndexArtistsRequest $request): Response
    {
        $organization = $request->user()->primaryOrganization();
        $event = $request->user()->effectiveEvent($organization);
        $filters = $request->validated();
        $search = $filters['search'] ?? '';
        $labelIds = $filters['labels'] ?? [];

        $engagements = ArtistEngagement::query()
            ->where('event_id', $event?->id)
            ->whereHas('artist', function (Builder $query) use ($organization, $search, $labelIds) {
                $query->where('organization_id', $organization->id)
                    ->when($search !== '', fn (Builder $query) => $query->whereLike('name', '%'.$search.'%'));

                foreach ($labelIds as $labelId) {
                    $query->whereHas('labels', fn (Builder $query) => $query->whereKey($labelId));
                }
            })
            ->with(['artist.labels' => fn ($query) => $query->orderBy('name'), 'artistType'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Artists/Index', [
            'engagements' => ArtistEngagementResource::collection($engagements),
            'labels' => $organization->artistLabels()->orderBy('name')->get(['id', 'name', 'color']),
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
        $event->ensureWritable($organization);
        $data = $request->validated();

        DB::transaction(function () use ($organization, $event, $data) {
            // Serialize additions with event locking and duplicate submissions.
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable($organization);
            $artist = $this->findOrCreateArtist($organization, $data['name']);

            if ($artist->engagements()->where('event_id', $event->id)->exists()) {
                throw ValidationException::withMessages(['name' => __('artists.errors.already_added')]);
            }

            $artist->engagements()->create([
                'event_id' => $event->id,
                'artist_type_id' => $data['artist_type_id'] ?? null,
                'status' => $data['status'] ?? 'idea',
            ]);
            $labelIds = $data['label_ids'] ?? [];
            foreach ($data['new_labels'] ?? [] as $label) {
                $labelIds[] = $this->findOrCreateLabel(
                    $organization,
                    $label['name'],
                    $label['color'],
                )->id;
            }

            // Labels belong to the reusable artist; preserve assignments from previous events.
            $artist->labels()->syncWithoutDetaching($labelIds);
        });

        return redirect()->route('artists.index')
            ->with('success', __('artists.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    private function findOrCreateArtist(Organization $organization, string $name): Artist
    {
        $existing = $organization->artists()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return $organization->artists()->create(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            $artist = $organization->artists()
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($artist) {
                return $artist;
            }

            throw ValidationException::withMessages([
                'name' => __('artists.errors.name_taken'),
            ]);
        }
    }

    private function findOrCreateLabel(Organization $organization, string $name, string $color): ArtistLabel
    {
        $existing = $organization->artistLabels()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return $organization->artistLabels()->create([
                'name' => $name,
                'color' => $color,
            ]);
        } catch (UniqueConstraintViolationException) {
            $label = $organization->artistLabels()
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($label) {
                return $label;
            }

            throw ValidationException::withMessages([
                'new_labels' => __('artists.errors.label_taken'),
            ]);
        }
    }
}
