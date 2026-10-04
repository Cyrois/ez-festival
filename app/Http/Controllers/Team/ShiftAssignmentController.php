<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\DestroyShiftAssignmentRequest;
use App\Http\Requests\Team\IndexShiftAssignmentCandidatesRequest;
use App\Http\Requests\Team\StoreShiftAssignmentRequest;
use App\Http\Requests\Team\UpdateShiftAssignmentRequest;
use App\Http\Resources\ShiftAssignmentCandidateResource;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Repositories\ShiftAssignmentRepository;
use App\Services\ShiftAssignmentService;
use App\Support\ShiftReturnContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ShiftAssignmentController extends Controller
{
    public function __construct(private readonly ShiftAssignmentService $assignments, private readonly ShiftAssignmentRepository $roster) {}

    public function index(IndexShiftAssignmentCandidatesRequest $request, Shift $shift): AnonymousResourceCollection
    {
        Gate::authorize('scheduling.edit');

        return ShiftAssignmentCandidateResource::collection($this->roster->candidates($shift, $request->validated()));
    }

    public function store(StoreShiftAssignmentRequest $request, Event $event, Shift $shift): RedirectResponse
    {
        Gate::authorize('scheduling.edit');
        $data = $request->validated();
        $assignment = $this->assignments->create($shift, ShiftReturnContext::without($data));
        $assignment->load(['role:id,name', 'teamEngagement.person:id,name']);

        return (($data['return_to_schedule'] ?? false)
            ? redirect()->route('team.scheduling', ShiftReturnContext::schedulingParameters($data, $shift->starts_at->format('Y-m-d')))
            : redirect()->route('team.shifts.show', ['shift' => $shift, ...ShiftReturnContext::from($data)]))
            ->with('success', __('team.scheduling.assignments.toast.created', ['name' => $assignment->teamEngagement->person->name, 'role' => $assignment->role->name]))
            ->with('success_title', __('toast.saved_title'));
    }

    public function update(UpdateShiftAssignmentRequest $request, Event $event, Shift $shift, ShiftAssignment $assignment): RedirectResponse
    {
        Gate::authorize('scheduling.edit');
        $data = $request->validated();
        $this->assignments->updateHours($shift, $assignment, ShiftReturnContext::without($data));

        return redirect()->route('team.shifts.show', ['shift' => $shift, ...ShiftReturnContext::from($data)])
            ->with('success', __('team.scheduling.assignments.toast.hours_updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function destroy(DestroyShiftAssignmentRequest $request, Event $event, Shift $shift, ShiftAssignment $assignment): RedirectResponse
    {
        Gate::authorize('scheduling.edit');
        $this->assignments->delete($shift, $assignment);

        return redirect()->route('team.shifts.show', ['shift' => $shift, ...ShiftReturnContext::from($request->validated())])
            ->with('success', __('team.scheduling.assignments.toast.removed'))
            ->with('success_title', __('toast.saved_title'));
    }
}
