<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\AdjustEntitlementItemRequest;
use App\Http\Requests\Credentials\DestroyEntitlementItemRequest;
use App\Http\Requests\Credentials\EditEntitlementItemRequest;
use App\Http\Requests\Credentials\StoreEntitlementItemRequest;
use App\Http\Requests\Credentials\UpdateEntitlementItemRequest;
use App\Http\Resources\EntitlementItemResource;
use App\Http\Resources\IssuedEntitlementResource;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\IssuedEntitlement;
use App\Services\EntitlementItemService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntitlementItemController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly EntitlementItemService $items,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');
        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Entitlements', [
            'items' => EntitlementItemResource::collection(
                $event->entitlementItems()
                    ->with('labels')
                    ->withSum('adjustments as balance', 'delta')
                    ->orderBy('name')
                    ->get(),
            )->resolve(),
            'labels' => $event->entitlementItemLabels()->orderBy('name')->get(['id', 'name', 'color']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function show(EntitlementItem $entitlementItem): Response
    {
        Gate::authorize('view-credentials');
        $event = $this->eventContext->requireCurrent(request()->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);

        return Inertia::render('Credentials/EntitlementView', [
            'item' => (new EntitlementItemResource(
                $entitlementItem->load('labels')->loadSum('adjustments as balance', 'delta'),
            ))->resolve(),
            'issued' => IssuedEntitlementResource::collection(
                IssuedEntitlement::query()
                    ->where('entitlement_item_id', $entitlementItem->id)
                    ->with(['expectedEntitlement.passAssignment.passType', 'issuedBy'])
                    ->latest('issued_at')
                    ->get(),
            )->resolve(),
        ]);
    }

    public function edit(EditEntitlementItemRequest $request, EntitlementItem $entitlementItem): Response
    {
        $event = $this->eventContext->requireWritable($request->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);

        return Inertia::render('Credentials/EditEntitlement', [
            'event' => $event->only('id', 'name'),
            'item' => (new EntitlementItemResource($entitlementItem->load('labels')))->resolve(),
            'labels' => $event->entitlementItemLabels()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreEntitlementItemRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->items->create($event, $request->validated(), $request->user());

        return $this->redirectWithSuccess('credentials.entitlements.toast.created');
    }

    public function update(
        UpdateEntitlementItemRequest $request,
        Event $event,
        EntitlementItem $entitlementItem,
    ): RedirectResponse {
        $this->ensureCurrentItem($request->user(), $event, $entitlementItem);
        $this->items->update($entitlementItem, $request->validated());

        return $this->redirectWithSuccess('credentials.entitlements.toast.updated');
    }

    public function adjust(
        AdjustEntitlementItemRequest $request,
        Event $event,
        EntitlementItem $entitlementItem,
    ): RedirectResponse {
        $this->ensureCurrentItem($request->user(), $event, $entitlementItem);
        $data = $request->validated();
        $delta = $data['direction'] === 'remove' ? -(int) $data['quantity'] : (int) $data['quantity'];

        $this->items->adjust($entitlementItem, $delta, $data['reason'] ?? null, $request->user());

        return $this->redirectWithSuccess('credentials.entitlements.toast.adjusted');
    }

    public function destroy(DestroyEntitlementItemRequest $request, Event $event, EntitlementItem $entitlementItem): RedirectResponse
    {
        $this->ensureCurrentItem($request->user(), $event, $entitlementItem);
        $this->items->destroy($entitlementItem);

        return $this->redirectWithSuccess('credentials.entitlements.toast.deleted');
    }

    private function ensureCurrentItem($user, Event $event, EntitlementItem $item): void
    {
        $this->eventContext->requireCurrentEvent($user, $event, writable: true);
        abort_unless($item->event_id === $event->id, 404);
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('credentials.entitlements')
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
