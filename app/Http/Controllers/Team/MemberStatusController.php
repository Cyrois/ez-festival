<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateTeamMemberStatusRequest;
use App\Models\TeamEngagement;
use App\Services\TeamEngagementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class MemberStatusController extends Controller
{
    public function __construct(
        private readonly TeamEngagementService $engagements,
        private readonly EventContext $eventContext,
    ) {}

    public function update(
        UpdateTeamMemberStatusRequest $request,
        TeamEngagement $engagement,
    ): RedirectResponse {
        $engagement->loadMissing('event');
        $this->eventContext->requireCurrentEvent(
            $request->user(),
            $engagement->event,
            writable: true,
        );
        $this->engagements->updateStatus($engagement, $request->validated('status'));

        return back()->with('success', __('team.advancement.toast.status_updated'));
    }
}
