<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\StoreTypeRequest;
use App\Http\Requests\Setup\UpdateTypeRequest;
use App\Models\VendorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorTypeController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response|RedirectResponse
    {
        $organization = $this->organization();
        $event = $organization->defaultEvent();

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        return Inertia::render('Setup/VendorTypes', [
            'organization' => [
                'name' => $organization->name(),
            ],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'types' => VendorType::query()
                ->orderBy('id')
                ->get(['id', 'name']),
            'currentStep' => 3,
        ]);
    }

    public function store(StoreTypeRequest $request): RedirectResponse
    {
        VendorType::query()->create($request->validated());

        return redirect()->route('setup.vendor-types');
    }

    public function update(UpdateTypeRequest $request, VendorType $vendorType): RedirectResponse
    {
        $vendorType->update($request->validated());

        return redirect()->route('setup.vendor-types');
    }

    public function continue(Request $request): RedirectResponse
    {
        return redirect()->route('setup.artist-types');
    }

    public function skip(Request $request): RedirectResponse
    {
        return redirect()->route('setup.artist-types');
    }
}
