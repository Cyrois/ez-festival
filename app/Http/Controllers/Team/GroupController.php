<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\DestroyGroupRequest;
use App\Http\Requests\Team\StoreGroupRequest;
use App\Http\Requests\Team\UpdateGroupRequest;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

class GroupController extends Controller
{
    public function store(StoreGroupRequest $request, Event $event): RedirectResponse
    {
        $event->ensureWritable();

        $event->groups()->create($request->validated());

        return redirect()->route('team.configure')
            ->with('success', __('team.configure.groups.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function update(UpdateGroupRequest $request, Event $event, Group $group): RedirectResponse
    {
        abort_unless((int) $group->event_id === (int) $event->id, 404);

        $event->ensureWritable();
        $group->update($request->validated());

        return redirect()->route('team.configure')
            ->with('success', __('team.configure.groups.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function destroy(DestroyGroupRequest $request, Event $event, Group $group): RedirectResponse
    {
        abort_unless((int) $group->event_id === (int) $event->id, 404);

        $event->ensureWritable();
        $group->delete();

        return redirect()->route('team.configure')
            ->with('success', __('team.configure.groups.toast.deleted'))
            ->with('success_title', __('toast.saved_title'));
    }
}
