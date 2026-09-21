<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\CreatePassRequest;
use App\Http\Requests\Credentials\EditPassRequest;
use App\Http\Requests\Credentials\StorePassRequest;
use App\Http\Requests\Credentials\UpdatePassRequest;
use App\Http\Resources\PassResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassLabel;
use App\Services\PassService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PassController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly PassService $passService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');

        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Passes', [
            'passes' => PassResource::collection(
                $event->passes()->with('labels')->withCount('assignments')->orderBy('name')->get(),
            )->resolve(),
            'labels' => PassLabel::query()
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function create(CreatePassRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Credentials/CreatePass', [
            'event' => $event->only('id', 'name'),
            'labels' => PassLabel::query()
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'labelColors' => PassLabel::COLORS,
            'customFields' => CustomField::query()
                ->forTarget(CustomField::TARGET_PASS)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'label', 'type', 'required', 'options']),
            'pass' => null,
        ]);
    }

    public function edit(EditPassRequest $request, Pass $pass): Response
    {
        $event = $this->eventContext->requireWritable($request->user());
        abort_unless($pass->event_id === $event->id, 404);
        $pass->load(['labels', 'customFieldValues.customField']);

        return Inertia::render('Credentials/CreatePass', [
            'event' => $event->only('id', 'name'),
            'labels' => PassLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'labelColors' => PassLabel::COLORS,
            'customFields' => CustomField::query()
                ->forTarget(CustomField::TARGET_PASS)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'label', 'type', 'required', 'options']),
            'pass' => [
                'id' => $pass->id,
                'name' => $pass->name,
                'max_assignments' => $pass->max_assignments,
                'label_ids' => $pass->labels->pluck('id')->all(),
                'custom_fields' => $pass->customFieldValues
                    ->filter(fn ($value): bool => $value->customField !== null)
                    ->mapWithKeys(fn ($value): array => [$value->custom_field_id => $value->typedValue($value->customField)])
                    ->all(),
            ],
        ]);
    }

    public function store(
        StorePassRequest $request,
        Event $event,
    ): RedirectResponse {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->passService->create($event, $request->validated(), $request->customFields());

        return redirect()->route('credentials.passes')
            ->with('success', __('credentials.passes.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function update(
        UpdatePassRequest $request,
        Event $event,
        Pass $pass,
    ): RedirectResponse {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        abort_unless($pass->event_id === $event->id, 404);
        $this->passService->update($pass, $request->validated(), $request->customFields());

        return redirect()->route('credentials.passes')
            ->with('success', __('credentials.passes.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }
}
