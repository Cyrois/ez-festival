<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateTeamEngagementGroupRequest;
use App\Models\Event;
use App\Models\TeamEngagement;
use Illuminate\Http\RedirectResponse;

class TeamEngagementGroupController extends Controller
{
    public function update(
        UpdateTeamEngagementGroupRequest $request,
        Event $event,
        TeamEngagement $engagement,
    ): RedirectResponse {
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);

        $event->ensureWritable();
        $engagement->update($request->validated());

        return redirect()->back(fallback: route('team.configure'))
            ->with('success', __('team.configure.members.toast.assigned'))
            ->with('success_title', __('toast.saved_title'));
    }
}
