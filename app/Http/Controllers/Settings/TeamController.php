<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IndexGlobalTeamDataTableRequest;
use App\Http\Requests\Settings\LookupGlobalTeamPersonRequest;
use App\Http\Requests\Settings\StoreGlobalTeamPersonRequest;
use App\Http\Requests\Settings\UpdateGlobalTeamPersonRequest;
use App\Http\Resources\GlobalTeamDataTableResource;
use App\Http\Resources\PersonEmailLookupResource;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Repositories\GlobalTeamRepository;
use App\Services\GlobalTeamService;
use App\Services\PersonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly GlobalTeamRepository $team,
        private readonly GlobalTeamService $teamService,
        private readonly PersonService $people,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Team', [
            'hasAnyPeople' => $this->team->exists(),
        ]);
    }

    public function dataTable(IndexGlobalTeamDataTableRequest $request): GlobalTeamDataTableResource
    {
        $result = $this->team->dataTable(
            trim((string) $request->validated('search.value', '')),
            (int) $request->validated('start'),
            (int) $request->validated('length'),
            (int) $request->validated('order.0.column', 0),
            $request->validated('order.0.dir', 'asc'),
        );

        return new GlobalTeamDataTableResource([
            'draw' => (int) $request->validated('draw'),
            ...$result,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Settings/Team/Create', [
            'events' => $this->eventOptions(),
            'roles' => $this->roleOptions(),
            'statuses' => ['applied', 'reviewing', 'hired'],
        ]);
    }

    public function store(StoreGlobalTeamPersonRequest $request): RedirectResponse
    {
        $person = $this->teamService->create($request->validated());

        return redirect()->route('settings.team.show', $person)
            ->with('success', __('settings.team.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function show(Request $request, Person $person): Response
    {
        abort_unless($person->teamEngagements()->whereNotNull('role_id')->exists(), 404);
        $person->load(['teamEngagements.role']);
        $engagements = $person->teamEngagements->keyBy('event_id');

        return Inertia::render('Settings/Team/Show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'can_log_in' => $person->can_log_in,
                'login_disable_reason' => $this->teamService->loginLockReason($request->user(), $person),
            ],
            'events' => collect($this->eventOptions())->map(function (array $event) use ($engagements): array {
                $engagement = $engagements->get($event['id']);

                return [
                    ...$event,
                    'role_id' => $engagement?->role_id,
                    'role' => $engagement?->role?->only(['id', 'name', 'active']),
                    'status' => $engagement?->status,
                ];
            })->all(),
            'roles' => $this->roleOptions(),
            'statuses' => ['applied', 'reviewing', 'hired'],
        ]);
    }

    public function update(UpdateGlobalTeamPersonRequest $request, Person $person): RedirectResponse
    {
        abort_unless($person->teamEngagements()->whereNotNull('role_id')->exists(), 404);
        $this->teamService->update($person, $request->validated());

        if (! $person->teamEngagements()->whereNotNull('role_id')->exists()) {
            return redirect()->route('settings.team')
                ->with('success', __('settings.team.toast.updated'))
                ->with('success_title', __('toast.saved_title'));
        }

        return redirect()->route('settings.team.show', $person)
            ->with('success', __('settings.team.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function lookup(LookupGlobalTeamPersonRequest $request): PersonEmailLookupResource
    {
        $person = $this->people->findByEmail($request->validated('email'));

        return new PersonEmailLookupResource([
            'person' => $person,
            'on_global_team' => $person?->teamEngagements()->whereNotNull('role_id')->exists() ?? false,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function eventOptions(): array
    {
        return Event::query()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_on' => $event->starts_on->toDateString(),
                'ends_on' => $event->ends_on->toDateString(),
                'locked' => $event->isLocked(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function roleOptions(): array
    {
        return Role::query()
            ->where('active', true)
            ->orderBy('name_key')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
            ->all();
    }
}
