<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftConfigLocationResource;
use App\Http\Resources\ShiftRoleResource;
use App\Http\Resources\ShiftTemplateResource;
use App\Support\EventContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConfigureController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(): Response
    {
        Gate::authorize('view-team');

        $event = $this->eventContext->requireCurrent(request()->user());

        $locations = $event->locations()
            ->with(['shiftTemplates' => fn ($query) => $query
                ->with(['roleLines.shiftRole', 'location'])
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $templates = $locations
            ->flatMap(fn ($location) => $location->shiftTemplates)
            ->values();

        return Inertia::render('Team/Configure', [
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
