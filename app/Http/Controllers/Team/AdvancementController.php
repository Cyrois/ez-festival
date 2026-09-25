<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamAdvancementRequest;
use App\Http\Resources\TeamEngagementResource;
use App\Models\TeamEngagement;
use App\Repositories\TeamEngagementRepository;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdvancementController extends Controller
{
    public function __construct(private readonly TeamEngagementRepository $engagements) {}

    public function index(IndexTeamAdvancementRequest $request): Response
    {
        $event = $request->user()->effectiveEvent();
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $employmentTypes = $filters['employment_types'] ?? [];
        $view = $filters['view'] ?? 'columns';

        $engagements = $view === 'columns'
            ? $this->engagements->all($event, $search, $employmentTypes)
            : $this->engagements->paginate($event, $search, $employmentTypes);

        return Inertia::render('Team/Advancement', [
            'engagements' => TeamEngagementResource::collection($engagements),
            'statuses' => TeamEngagement::STATUSES,
            'employmentTypes' => TeamEngagement::EMPLOYMENT_TYPES,
            'statusCounts' => $this->engagements->statusCounts($event, $search, $employmentTypes),
            'filters' => [
                'search' => $search,
                'employment_types' => $employmentTypes,
                'view' => $view,
            ],
            'event' => $event?->only('id', 'name', 'locked'),
            'canWrite' => $event !== null && ! $event->isLocked() && Gate::allows('manage-team'),
        ]);
    }
}
