<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\CreateCredentialPassRequest;
use App\Http\Requests\Credentials\StoreCredentialPassRequest;
use App\Http\Resources\CredentialPassResource;
use App\Models\CredentialPassLabel;
use App\Models\CustomField;
use App\Models\Event;
use App\Services\CredentialPassService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CredentialPassController extends Controller
{
    public function __construct(private readonly EventContext $eventContext) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');

        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Passes', [
            'passes' => CredentialPassResource::collection(
                $event->credentialPasses()->orderBy('name')->get(),
            )->resolve(),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function create(CreateCredentialPassRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Credentials/CreatePass', [
            'event' => $event->only('id', 'name'),
            'labels' => CredentialPassLabel::query()
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'labelColors' => CredentialPassLabel::COLORS,
            'customFields' => CustomField::query()
                ->forTarget(CustomField::TARGET_CREDENTIAL_PASS)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'label', 'type', 'required', 'options']),
        ]);
    }

    public function store(
        StoreCredentialPassRequest $request,
        Event $event,
        CredentialPassService $credentialPassService,
    ): RedirectResponse {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $credentialPassService->create($event, $request->validated(), $request->customFields());

        return redirect()->route('credentials.passes')
            ->with('success', __('credentials.passes.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }
}
