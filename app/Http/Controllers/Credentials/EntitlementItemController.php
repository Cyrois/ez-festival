<?php

namespace App\Http\Controllers\Credentials;

use App\Enums\StockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\AdjustEntitlementItemRequest;
use App\Http\Requests\Credentials\DestroyEntitlementItemRequest;
use App\Http\Requests\Credentials\StoreEntitlementItemRequest;
use App\Http\Requests\Credentials\UpdateEntitlementItemRequest;
use App\Http\Resources\EntitlementItemResource;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Queries\InventoryQuery;
use App\Services\EntitlementItemService;
use App\Support\EntitlementLabels;
use App\Support\EventContext;
use App\Support\LabelColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntitlementItemController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly EntitlementItemService $items,
        private readonly InventoryQuery $inventory,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-entitlements');
        $event = $this->eventContext->requireCurrent(request()->user());

        return Inertia::render('Credentials/Entitlements', [
            'items' => EntitlementItemResource::collection(
                $this->items->list($event),
            )->resolve(),
            'labels' => EntitlementLabels::ordered($event->entitlementItemLabels()->get(['id', 'name', 'color'])),
            'labelColors' => LabelColors::ALL,
            'locations' => $event->locations()->orderBy('name')->get(['id', 'name']),
            'canWrite' => ! $event->isLocked(),
            'canEditItems' => Gate::allows('credentials.entitlements.edit', $event),
            'canAdjustStock' => Gate::allows('credentials.stock.adjust', $event),
        ]);
    }

    public function show(EntitlementItem $entitlementItem): Response
    {
        Gate::authorize('view-entitlements');
        $event = $this->eventContext->requireCurrent(request()->user());
        abort_unless($entitlementItem->event_id === $event->id, 404);

        return Inertia::render('Credentials/EditEntitlement', $this->pageProps($event, $entitlementItem));
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

        return $this->redirectWithSuccess(
            'credentials.entitlements.toast.updated',
            route('credentials.entitlements.show', $entitlementItem),
        );
    }

    public function adjust(
        AdjustEntitlementItemRequest $request,
        Event $event,
        EntitlementItem $entitlementItem,
    ): RedirectResponse {
        $this->ensureCurrentItem($request->user(), $event, $entitlementItem);
        $data = $request->validated();
        $delta = $data['direction'] === 'remove' ? -(int) $data['quantity'] : (int) $data['quantity'];

        $this->items->adjust(
            $entitlementItem,
            (int) $data['location_id'],
            $delta,
            $data['reason'] ?? null,
            $request->user(),
        );

        return $this->redirectWithSuccess(
            'credentials.entitlements.toast.adjusted',
            route('credentials.entitlements.show', $entitlementItem),
        );
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

    /** @return array<string, mixed> */
    private function pageProps(Event $event, EntitlementItem $item): array
    {
        $item->load('labels');
        $balances = $this->items->balanceByLocation($item);
        $inStock = $this->items->balance($item);
        $eventLocations = $event->locations()
            ->orderBy('name')
            ->get(['id', 'name']);
        $locations = $eventLocations
            ->map(fn ($location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'in_stock' => $balances[$location->id] ?? 0,
            ]);
        $requestedLocationId = request()->integer('loc');

        return [
            'event' => $event->only('id', 'name', 'timezone'),
            'item' => (new EntitlementItemResource($item))->resolve(),
            'labels' => EntitlementLabels::ordered($event->entitlementItemLabels()->get(['id', 'name', 'color'])),
            'labelColors' => LabelColors::ALL,
            'stats' => [
                'in_stock' => $inStock,
                'expected' => $this->items->expectedCount($item),
                'issued' => $this->items->issuedCount($item),
                'held_by_people' => (int) $this->inventory->peopleHoldingItem($item)->reorder()->sum('balance'),
            ],
            'locations' => $locations,
            'history_url' => route('credentials.entitlements.history', $item),
            'history_options_url' => route('credentials.entitlements.history.options', $item),
            'holdings_url' => route('credentials.entitlements.holdings', $item),
            'history_export_url' => Gate::allows('inventory.manage', $event) ? route('check-in.history.export', ['item_id' => $item->id]) : null,
            'movementTypes' => array_column(StockMovement::cases(), 'value'),
            'pass_usage' => $this->items->passLineUsage($item),
            'locations_for_adjust' => $eventLocations
                ->map(fn ($location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                ])
                ->values(),
            'adjust_location_id' => $locations->contains('id', $requestedLocationId)
                ? $requestedLocationId
                : null,
            'open_adjust' => ! $event->isLocked() && Gate::allows('credentials.stock.adjust', $event)
                && (request()->boolean('adjust') || $requestedLocationId > 0),
            'is_read_only' => $event->isLocked(),
            'canEditItems' => Gate::allows('credentials.entitlements.edit', $event),
            'canAdjustStock' => Gate::allows('credentials.stock.adjust', $event),
        ];
    }

    private function redirectWithSuccess(string $message, ?string $destination = null): RedirectResponse
    {
        return redirect($destination ?? route('credentials.entitlements'))
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
