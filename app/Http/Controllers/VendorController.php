<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vendors\CreateVendorRequest;
use App\Http\Requests\Vendors\IndexVendorsRequest;
use App\Http\Requests\Vendors\StoreVendorNoteRequest;
use App\Http\Requests\Vendors\StoreVendorRequest;
use App\Http\Requests\Vendors\UpdateVendorRequest;
use App\Http\Resources\VendorEngagementNoteResource;
use App\Http\Resources\VendorResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\VendorEngagement;
use App\Models\VendorType;
use App\Repositories\VendorRepository;
use App\Services\VendorService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly VendorService $vendorService,
        private readonly EventContext $eventContext,
    ) {}

    public function index(IndexVendorsRequest $request): Response
    {
        $event = $request->user()->effectiveEvent();
        $filters = $request->validated();
        $search = $filters['search'] ?? '';

        return Inertia::render('Vendors/Index', [
            'vendors' => VendorResource::collection($this->vendors->paginateFor($event, $search)),
            'filters' => ['search' => $search],
            'event' => $event?->only('id', 'name', 'locked'),
        ]);
    }

    public function checkIn(): Response
    {
        return Inertia::render('Vendors/CheckIn');
    }

    public function create(CreateVendorRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Vendors/Create', [
            'event' => $event->only('id', 'name'),
            'types' => VendorType::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => VendorEngagement::STATUSES,
            'customFields' => CustomField::query()
                ->forTarget(CustomField::TARGET_VENDOR)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'label', 'type', 'required', 'options']),
        ]);
    }

    public function store(StoreVendorRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->vendorService->addToEvent(
            $event,
            $request->validated(),
            $request->customFields(),
        );

        return redirect()->route('vendors.advancing')
            ->with('success', __('vendors.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function view(VendorEngagement $engagement): Response
    {
        $event = $this->resolveEventContext($engagement);
        $engagement->load([
            'vendor',
            'vendorType',
            'people' => fn ($query) => $query->orderByDesc('vendor_engagement_people.is_primary')->orderBy('people.name'),
            'passAssignments.pass',
            'passAssignments.person',
        ]);
        $notes = $engagement->notes()->with('user:id,name,email')->latest('created_at')->latest('id')->get();

        return Inertia::render('Vendors/View', [
            'engagement' => (new VendorResource($engagement))->resolve(),
            'notes' => VendorEngagementNoteResource::collection($notes)->resolve(),
            'event' => $event->only('id', 'name', 'locked', 'timezone'),
            'types' => VendorType::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => VendorEngagement::STATUSES,
            'passes' => $event->passes()->withCount('assignments')->orderBy('name')->get(['id', 'name', 'max_assignments']),
            'canWrite' => ! $event->isLocked(),
        ]);
    }

    public function update(UpdateVendorRequest $request, VendorEngagement $engagement): RedirectResponse
    {
        $this->resolveEventContext($engagement, writable: true);
        $this->vendorService->updateEngagement(
            $engagement,
            $request->validated(),
            $request->customFields(),
        );

        return redirect()->route('vendors.view', $engagement)
            ->with('success', __('vendors.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function storeNote(StoreVendorNoteRequest $request, VendorEngagement $engagement): RedirectResponse
    {
        $this->resolveEventContext($engagement, writable: true);
        $this->vendorService->addNote($engagement, $request->user(), $request->validated('body'));

        return redirect()->route('vendors.view', $engagement)
            ->with('success', __('vendors.toast.note_posted'))
            ->with('success_title', __('toast.saved_title'));
    }

    private function resolveEventContext(VendorEngagement $engagement, bool $writable = false): Event
    {
        $event = request()->user()->effectiveEvent();
        abort_if($event === null, 404);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        if ($writable) {
            $event->ensureWritable();
        }

        return $event;
    }
}
