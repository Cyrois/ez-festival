<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\ContinueArtistTypesRequest;
use App\Http\Requests\Setup\StoreTypeRequest;
use App\Http\Requests\Setup\UpdateTypeRequest;
use App\Models\ArtistType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtistTypeController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response|RedirectResponse
    {
        $organization = $this->organization();
        $event = $organization->defaultEvent();

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        return Inertia::render('Setup/ArtistTypes', [
            'organization' => [
                'name' => $organization->name(),
            ],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'types' => ArtistType::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'sort_order']),
            'currentStep' => 4,
        ]);
    }

    public function store(StoreTypeRequest $request): RedirectResponse
    {
        ArtistType::query()->create($request->validated());

        return redirect()->route('setup.artist-types');
    }

    public function update(UpdateTypeRequest $request, ArtistType $artistType): RedirectResponse
    {
        $artistType->update($request->validated());

        return redirect()->route('setup.artist-types');
    }

    public function destroy(Request $request, ArtistType $artistType): RedirectResponse
    {
        $artistType->delete();

        return redirect()->route('setup.artist-types');
    }

    public function continue(ContinueArtistTypesRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach ($data['suggestions'] ?? [] as $item) {
            ArtistType::query()->create([
                'name' => $item['name'],
            ]);
        }

        return redirect()->route('setup.ready');
    }

    public function skip(Request $request): RedirectResponse
    {
        return redirect()->route('setup.ready');
    }
}
