<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreShiftRequest;
use App\Http\Resources\ShiftCopyPreviewResource;
use App\Models\Event;
use App\Support\EventContext;
use App\Support\ShiftCopyAssignments;

class ShiftCopyPreviewController extends Controller
{
    public function store(StoreShiftRequest $request, Event $event, EventContext $eventContext): ShiftCopyPreviewResource
    {
        $eventContext->requireCurrentEvent($request->user(), $event);
        $data = $request->validated();
        $rows = ShiftCopyAssignments::resolve($event, $data);

        return new ShiftCopyPreviewResource(ShiftCopyAssignments::overlaps($event, $data, $rows));
    }
}
