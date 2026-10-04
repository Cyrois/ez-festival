<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexShiftAssignmentOverlapsRequest;
use App\Http\Resources\ShiftAssignmentOverlapResource;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ShiftAssignmentOverlapController extends Controller
{
    public function index(IndexShiftAssignmentOverlapsRequest $request, Shift $shift, ShiftAssignment $assignment): AnonymousResourceCollection
    {
        Gate::authorize('scheduling.edit');
        [$start, $end] = ShiftAssignmentHours::resolve($shift, $request->validated());
        $others = ShiftAssignmentOverlaps::forMembers($shift, [$assignment->team_engagement_id], $start, $end);

        return ShiftAssignmentOverlapResource::collection(ShiftAssignmentOverlaps::warnings(
            $others->get($assignment->team_engagement_id, collect()), $start, $end, $shift->event->timezone,
        ));
    }
}
