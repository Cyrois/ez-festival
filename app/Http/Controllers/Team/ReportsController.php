<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamReportsRequest;
use App\Repositories\GroupRepository;
use App\Support\EventContext;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(IndexTeamReportsRequest $request, EventContext $eventContext, GroupRepository $groups): Response
    {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();

        return Inertia::render('Team/Reports', [
            'filters' => [
                'group_id' => isset($data['group_id']) ? (int) $data['group_id'] : null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ],
            'groupOptions' => $groups->optionsFor($event)->map(fn ($group): array => ['group_id' => $group->id, 'name' => $group->name]),
        ]);
    }
}
