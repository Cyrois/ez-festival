<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexShiftDataTableRequest;
use App\Http\Resources\ShiftResource;
use App\Http\Responses\Team\ShiftDataTableResponse;
use App\Repositories\ShiftRepository;
use App\Support\EventContext;

class ShiftDataTableController extends Controller
{
    public function __construct(private readonly ShiftRepository $shifts) {}

    public function index(
        IndexShiftDataTableRequest $request,
        EventContext $eventContext,
    ): ShiftDataTableResponse {
        $event = $eventContext->requireCurrent($request->user());
        $validated = $request->validated();

        $result = $this->shifts->dataTable(
            $event,
            search: trim((string) data_get($validated, 'search.value', '')),
            orderColumn: (int) data_get($validated, 'order.0.column', 2),
            orderDirection: (string) data_get($validated, 'order.0.dir', 'asc'),
            start: (int) ($validated['start'] ?? 0),
            length: (int) ($validated['length'] ?? 25),
        );

        return new ShiftDataTableResponse(
            draw: (int) ($validated['draw'] ?? 0),
            recordsTotal: $result['total'],
            recordsFiltered: $result['filtered'],
            data: ShiftResource::collection($result['rows'])->resolve($request),
        );
    }
}
