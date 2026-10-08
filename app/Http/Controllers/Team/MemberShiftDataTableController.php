<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexMemberShiftsRequest;
use App\Http\Resources\MemberShiftResource;
use App\Http\Responses\Team\ShiftDataTableResponse;
use App\Models\TeamEngagement;
use App\Repositories\ShiftAssignmentRepository;
use App\Support\EventContext;

class MemberShiftDataTableController extends Controller
{
    public function index(
        IndexMemberShiftsRequest $request,
        TeamEngagement $engagement,
        EventContext $eventContext,
        ShiftAssignmentRepository $assignments,
    ): ShiftDataTableResponse {
        $event = $eventContext->requireCurrentEvent($request->user(), $engagement->event);
        $validated = $request->validated();
        $result = $assignments->memberDataTable(
            $event,
            $engagement,
            search: trim((string) data_get($validated, 'search.value', '')),
            orderColumn: (int) data_get($validated, 'order.0.column', 0),
            orderDirection: (string) data_get($validated, 'order.0.dir', 'asc'),
            start: (int) ($validated['start'] ?? 0),
            length: (int) ($validated['length'] ?? 25),
        );

        return new ShiftDataTableResponse(
            draw: (int) ($validated['draw'] ?? 0),
            recordsTotal: $result['total'],
            recordsFiltered: $result['filtered'],
            data: MemberShiftResource::collection($result['rows'])->resolve($request),
        );
    }
}
