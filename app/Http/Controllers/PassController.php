<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\CreatePassRequest;
use App\Http\Requests\Credentials\StorePassRequest;
use App\Http\Resources\PassResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\PassLabel;
use App\Services\PassService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PassController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');

        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Passes', [
            'passes' => PassResource::collection(
                $event->passes()->orderBy('name')->get(),
            )->resolve(),
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
        ]);
    }

    public function store(
        StorePassRequest $request,
        Event $event,
        PassService $passService,
    ): RedirectResponse {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $passService->create($event, $request->validated(), $request->customFields());

        return redirect()->route('credentials.passes')
            ->with('success', __('credentials.passes.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }
}
