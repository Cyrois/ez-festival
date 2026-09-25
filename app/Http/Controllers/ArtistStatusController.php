<?php

namespace App\Http\Controllers;

use App\Http\Requests\Artists\UpdateArtistStatusRequest;
use App\Models\ArtistEngagement;
use App\Services\ArtistService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class ArtistStatusController extends Controller
{
    public function __construct(
        private readonly ArtistService $artistService,
        private readonly EventContext $eventContext,
    ) {}

    public function update(UpdateArtistStatusRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        $engagement->loadMissing('event');
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        $this->artistService->updateStatus($engagement, $request->validated('status'));

        return back()->with('success', __('artists.toast.status_updated'));
    }
}
