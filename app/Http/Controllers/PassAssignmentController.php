<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\UpdatePassAssignmentRequest;
use App\Models\PassAssignment;
use App\Models\Person;
use App\Services\PassAssignmentService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PassAssignmentController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly PassAssignmentService $assignments,
    ) {}

    public function update(UpdatePassAssignmentRequest $request, PassAssignment $assignment): RedirectResponse
    {
        $this->ensureCurrentAssignmentEvent($request, $assignment);
        $this->assignments->assignPerson($assignment, Person::query()->findOrFail($request->integer('person_id')));

        return back()->with('success', __('credentials.assignments.toast.assigned'));
    }

    public function destroy(PassAssignment $assignment): RedirectResponse
    {
        $this->ensureCurrentAssignmentEvent(request(), $assignment);
        $this->assignments->remove($assignment);

        return back()->with('success', __('credentials.assignments.toast.removed'));
    }

    private function ensureCurrentAssignmentEvent(Request $request, PassAssignment $assignment): void
    {
        $assignment->loadMissing(['artistEngagement.event', 'vendorEngagement.event', 'eventPatron.event']);
        $event = $assignment->artistEngagement?->event
            ?? $assignment->vendorEngagement?->event
            ?? $assignment->eventPatron?->event;
        abort_unless($event !== null, 404);
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
    }
}
