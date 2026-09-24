<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexGroupsRequest;
use App\Http\Resources\ShiftConfigLocationResource;
use App\Http\Resources\ShiftRoleResource;
use App\Http\Resources\ShiftTemplateResource;
use App\Models\Group;
use App\Support\EventContext;
use App\Support\SqlLike;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConfigureController extends Controller
{
    public function index(IndexGroupsRequest $request, EventContext $eventContext): Response
    {
        Gate::authorize('view-team');

        $event = $eventContext->requireCurrent($request->user());

        $locations = $event->locations()
            ->with(['shiftTemplates' => fn ($query) => $query
                ->with(['roleLines.shiftRole', 'location'])
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $templates = $locations
            ->flatMap(fn ($location) => $location->shiftTemplates)
            ->values();

        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $searchPattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        $groups = $event->groups()
            ->withCount('teamEngagements')
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    fn ($searchQuery) => $searchQuery
                        ->whereRaw("lower(name) like ? escape '!'", [$searchPattern])
                        ->orWhereRaw("lower(description) like ? escape '!'", [$searchPattern]),
                ),
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
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
            'locations' => ShiftConfigLocationResource::collection($locations)->resolve(),
            'templates' => ShiftTemplateResource::collection($templates)->resolve(),
            'shiftRoles' => ShiftRoleResource::collection(
                $event->shiftRoles()->orderBy('name')->get(),
            )->resolve(),
            'canWrite' => ! $event->isLocked() && Gate::allows('manage-shift-templates'),
            'settingsLocationsUrl' => route('settings.locations'),
        ]);
    }
}
