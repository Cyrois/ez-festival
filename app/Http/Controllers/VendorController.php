<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vendors\IndexVendorsRequest;
use App\Http\Requests\Vendors\StoreVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Event;
use App\Models\VendorEngagement;
use App\Models\VendorType;
use App\Repositories\VendorRepository;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly VendorService $vendorService,
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

    public function create(): Response
    {
        $event = request()->user()->effectiveEvent();
        abort_if($event === null, 404);
        $event->ensureWritable();

        return Inertia::render('Vendors/Create', [
            'event' => $event->only('id', 'name'),
            'types' => VendorType::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => VendorEngagement::STATUSES,
        ]);
    }

    public function store(StoreVendorRequest $request, Event $event): RedirectResponse
    {
        $effectiveEvent = $request->user()->effectiveEvent();
        abort_unless($effectiveEvent?->is($event), 404);
        $event->ensureWritable();
        $this->vendorService->addToEvent($event, $request->validated());

        return redirect()->route('vendors.advancing')
            ->with('success', __('vendors.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }
}
