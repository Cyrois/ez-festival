<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\CreatePassRequest;
use App\Http\Requests\Credentials\DestroyPassRequest;
use App\Http\Requests\Credentials\EditPassRequest;
use App\Http\Requests\Credentials\StorePassRequest;
use App\Http\Requests\Credentials\UpdatePassRequest;
use App\Http\Resources\PassTypeResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\PassType;
use App\Models\PassTypeLabel;
use App\Services\PassTypeService;
use App\Support\EventContext;
use App\Support\LabelColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PassTypeController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly PassTypeService $passTypes,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');
        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Passes', [
            'passes' => PassTypeResource::collection(
                $event->passTypes()->with('labels')->withCount('assignments')->orderBy('name')->get(),
            )->resolve(),
            'labels' => PassTypeLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function create(CreatePassRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Credentials/CreatePass', $this->formProps($event) + ['pass' => null]);
    }

    public function edit(EditPassRequest $request, PassType $passType): Response
    {
        $event = $this->eventContext->requireWritable($request->user());
        abort_unless($passType->event_id === $event->id, 404);
        $passType->load(['labels', 'customFieldValues.customField', 'entitlements']);

        return Inertia::render('Credentials/CreatePass', $this->formProps($event) + [
            'pass' => [
                'id' => $passType->id,
                'name' => $passType->name,
                'max_assignments' => $passType->max_assignments,
                'label_ids' => $passType->labels->pluck('id')->all(),
                'entitlement_item_ids' => $passType->entitlements->sortBy('sort_order')->pluck('entitlement_item_id')->all(),
                'custom_fields' => $passType->customFieldValues
                    ->filter(fn ($value): bool => $value->customField !== null)
                    ->mapWithKeys(fn ($value): array => [$value->custom_field_id => $value->typedValue($value->customField)])
                    ->all(),
            ],
        ]);
    }

    public function store(StorePassRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->passTypes->create($event, $request->validated(), $request->customFields());

        return $this->redirectWithSuccess('credentials.passes.toast.created');
    }

    public function update(UpdatePassRequest $request, Event $event, PassType $passType): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        abort_unless($passType->event_id === $event->id, 404);
        $this->passTypes->update($passType, $request->validated(), $request->customFields());

        return $this->redirectWithSuccess('credentials.passes.toast.updated');
    }

    public function destroy(DestroyPassRequest $request, Event $event, PassType $passType): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        abort_unless($passType->event_id === $event->id, 404);
        $this->passTypes->destroy($passType);

        return $this->redirectWithSuccess('credentials.passes.toast.deleted');
    }

    /** @return array<string, mixed> */
    private function formProps(Event $event): array
    {
        return [
            'event' => $event->only('id', 'name'),
            'labels' => PassTypeLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'labelColors' => LabelColors::ALL,
            'entitlementItems' => $event->entitlementItems()->orderBy('name')->get(['id', 'name']),
            'customFields' => CustomField::query()
                ->forTarget(CustomField::TARGET_PASS)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'label', 'type', 'required', 'options']),
        ];
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('credentials.passes')
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
