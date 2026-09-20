<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\StorePassAssignmentRequest;
use App\Models\Pass;
use App\Models\VendorEngagement;
use App\Services\PassAssignmentService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class VendorPassAssignmentController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly PassAssignmentService $assignments,
    ) {}

    public function store(StorePassAssignmentRequest $request, VendorEngagement $engagement): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        $this->assignments->give($engagement, Pass::query()->findOrFail($request->integer('pass_id')), $request->integer('quantity'));

        return back()->with('success', __('credentials.assignments.toast.given'));
    }
}
