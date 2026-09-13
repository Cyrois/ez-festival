<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
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
        $organization = $this->organization($request);

        if ($organization->activeEvent === null) {
            return redirect()->route('setup.event');
        }

        return Inertia::render('Setup/ArtistTypes', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'event' => $organization->activeEvent
                ? ['id' => $organization->activeEvent->id, 'name' => $organization->activeEvent->name]
                : null,
            'types' => $organization->artistTypes()
                ->orderBy('id')
                ->get(['id', 'name']),
            'currentStep' => 4,
        ]);
    }

    public function store(StoreTypeRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $organization->artistTypes()->create($request->validated());

        return redirect()->route('setup.artist-types');
    }

    public function update(UpdateTypeRequest $request, ArtistType $artistType): RedirectResponse
    {
        $organization = $this->organization($request);

        abort_unless($artistType->organization_id === $organization->id, 404);

        $artistType->update($request->validated());

        return redirect()->route('setup.artist-types');
    }

    public function destroy(Request $request, ArtistType $artistType): RedirectResponse
    {
        $organization = $this->organization($request);

        abort_unless($artistType->organization_id === $organization->id, 404);

        $artistType->delete();

        return redirect()->route('setup.artist-types');
    }

    public function continue(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $data = $request->validate([
            'suggestions' => ['sometimes', 'array'],
            'suggestions.*.name' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data['suggestions'] ?? [] as $item) {
            $organization->artistTypes()->create([
                'name' => $item['name'],
            ]);
        }

        return redirect()->route('setup.ready');
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.ready');
    }
}
