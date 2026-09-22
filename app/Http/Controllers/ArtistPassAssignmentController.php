<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\StorePassAssignmentRequest;
use App\Models\ArtistEngagement;
use App\Models\PassType;
use App\Services\PassAssignmentService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class ArtistPassAssignmentController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly PassAssignmentService $assignments,
    ) {}

    public function store(StorePassAssignmentRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        $this->assignments->give($engagement, PassType::query()->findOrFail($request->integer('pass_type_id')), $request->integer('quantity'));

        return back()->with('success', __('credentials.assignments.toast.given'));
    }
}
