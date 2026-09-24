<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\TeamEngagement;
use App\Support\EventContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConfigureController extends Controller
{
    public function index(Request $request, EventContext $eventContext): Response
    {
        Gate::authorize('view-team');

        $event = $eventContext->requireCurrent($request->user());

        $members = TeamEngagement::query()
            ->where('event_id', $event->id)
            ->with(['person:id,name,email', 'group:id,name'])
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TeamEngagement $engagement): array => [
                'id' => $engagement->id,
                'person' => [
                    'id' => $engagement->person->id,
                    'name' => $engagement->person->name,
                    'email' => $engagement->person->email,
                ],
                'group_id' => $engagement->group_id,
                'group_name' => $engagement->group?->name,
            ]);

        return Inertia::render('Team/Configure', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_locked' => $event->isLocked(),
            ],
            'groups' => $event->groups()
                ->withCount('teamEngagements')
                ->orderBy('name')
                ->get(['id', 'name']),
            'members' => $members,
            'canManage' => Gate::allows('manage-team'),
        ]);
    }
}
