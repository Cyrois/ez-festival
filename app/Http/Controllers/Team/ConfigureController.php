<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexGroupsRequest;
use App\Models\Group;
use App\Repositories\GroupRepository;
use App\Support\EventContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConfigureController extends Controller
{
    public function __construct(private readonly GroupRepository $groups) {}

    public function index(IndexGroupsRequest $request, EventContext $eventContext): Response
    {
        Gate::authorize('view-team');

        $event = $eventContext->requireCurrent($request->user());

        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $groups = $this->groups->paginateFor($event, $search)
            ->through(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'team_engagements_count' => $group->team_engagements_count,
            ]);

        return Inertia::render('Team/Configure', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_locked' => $event->isLocked(),
            ],
            'groups' => $groups,
            'filters' => ['search' => $search],
            'canManage' => Gate::allows('manage-team'),
        ]);
    }
}
