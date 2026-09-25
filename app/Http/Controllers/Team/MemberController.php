<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateTeamMemberRequest;
use App\Http\Requests\Team\StoreTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Http\Requests\Team\ViewTeamMemberRequest;
use App\Http\Resources\TeamEngagementResource;
use App\Models\Event;
use App\Models\Group;
use App\Models\TeamEngagement;
use App\Services\TeamEngagementService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly TeamEngagementService $engagements,
        private readonly EventContext $eventContext,
    ) {}

    public function create(CreateTeamMemberRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Team/CreateMember', [
            'event' => $event->only('id', 'name'),
            'groups' => $this->groups($event),
            'statuses' => TeamEngagement::STATUSES,
            'employmentTypes' => TeamEngagement::EMPLOYMENT_TYPES,
        ]);
    }

    public function store(StoreTeamMemberRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $engagement = $this->engagements->create($event, $request->validated());

        return redirect()->route('team.members.show', $engagement)
            ->with('success', __('team.advancement.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function show(ViewTeamMemberRequest $request, TeamEngagement $engagement): Response
    {
        $event = $this->resolveEvent($request, $engagement);
        $engagement->load(['person', 'group']);

        return Inertia::render('Team/Member', [
            'engagement' => (new TeamEngagementResource($engagement))->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'groups' => $this->groups($event),
            'statuses' => TeamEngagement::STATUSES,
            'employmentTypes' => TeamEngagement::EMPLOYMENT_TYPES,
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function update(UpdateTeamMemberRequest $request, TeamEngagement $engagement): RedirectResponse
    {
        $this->resolveEvent($request, $engagement, writable: true);
        $engagement->loadMissing('person');
        $this->engagements->update($engagement, $request->validated());

        return redirect()->route('team.members.show', $engagement)
            ->with('success', __('team.advancement.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    private function resolveEvent(
        ViewTeamMemberRequest|UpdateTeamMemberRequest $request,
        TeamEngagement $engagement,
        bool $writable = false,
    ): Event {
        $engagement->loadMissing('event');

        return $this->eventContext->requireCurrentEvent(
            $request->user(),
            $engagement->event,
            writable: $writable,
        );
    }

    private function groups(Event $event): mixed
    {
        return Group::query()
            ->whereBelongsTo($event)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
