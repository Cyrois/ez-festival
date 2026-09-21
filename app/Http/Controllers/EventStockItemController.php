<?php

namespace App\Http\Controllers;

use App\Http\Requests\Credentials\AdjustEventStockItemRequest;
use App\Http\Requests\Credentials\StoreEventStockItemRequest;
use App\Http\Requests\Credentials\UpdateEventStockItemRequest;
use App\Http\Resources\EventStockItemResource;
use App\Models\Event;
use App\Models\EventStockItem;
use App\Services\EventStockItemService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventStockItemController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly EventStockItemService $stockItems,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-credentials');
        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Entitlements', [
            'items' => EventStockItemResource::collection(
                $event->stockItems()->with('labels')->orderBy('name')->get(),
            )->resolve(),
            'labels' => $event->stockItemLabels()->orderBy('name')->get(['id', 'name', 'color']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function store(StoreEventStockItemRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->stockItems->create($event, $request->validated(), $request->user());

        return $this->redirectWithSuccess('credentials.entitlements.toast.created');
    }

    public function update(
        UpdateEventStockItemRequest $request,
        Event $event,
        EventStockItem $stockItem,
    ): RedirectResponse {
        $this->ensureCurrentStockItem($request->user(), $event, $stockItem);
        $this->stockItems->update($stockItem, $request->validated());

        return $this->redirectWithSuccess('credentials.entitlements.toast.updated');
    }

    public function adjust(
        AdjustEventStockItemRequest $request,
        Event $event,
        EventStockItem $stockItem,
    ): RedirectResponse {
        $this->ensureCurrentStockItem($request->user(), $event, $stockItem);
        $data = $request->validated();
        $quantity = (int) $data['quantity'];
        $delta = $data['direction'] === 'remove' ? -$quantity : $quantity;

        $this->stockItems->adjust($stockItem, $delta, $data['reason'], $request->user());

        return $this->redirectWithSuccess('credentials.entitlements.toast.adjusted');
    }

    private function ensureCurrentStockItem($user, Event $event, EventStockItem $stockItem): void
    {
        $this->eventContext->requireCurrentEvent($user, $event, writable: true);
        abort_unless($stockItem->event_id === $event->id, 404);
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('credentials.entitlements')
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
