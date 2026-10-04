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
    public function index(IndexShiftAssignmentOverlapsRequest $request, Shift $shift, ?ShiftAssignment $assignment = null): AnonymousResourceCollection
    {
        Gate::authorize('scheduling.edit');
        [$start, $end] = ShiftAssignmentHours::resolve($request->proposedShift(), $request->validated());
        $memberId = $assignment?->team_engagement_id ?? $request->validated('team_engagement_id');
        $others = ShiftAssignmentOverlaps::forMembers($shift, [$memberId], $start, $end);

        return ShiftAssignmentOverlapResource::collection(ShiftAssignmentOverlaps::warnings(
            $others->get($memberId, collect()), $start, $end, $shift->event->timezone,
        ));
    }
}
