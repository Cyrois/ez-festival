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

    public function show(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('Setup/VendorTypes', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'event' => $organization->activeEvent
                ? ['id' => $organization->activeEvent->id, 'name' => $organization->activeEvent->name]
                : null,
            'types' => $organization->vendorTypes()
                ->orderBy('id')
                ->get(['id', 'name']),
            'currentStep' => 3,
        ]);
    }

    public function store(StoreTypeRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $organization->vendorTypes()->create($request->validated());

        return redirect()->route('setup.vendor-types');
    }

    public function update(UpdateTypeRequest $request, VendorType $vendorType): RedirectResponse
    {
        $organization = $this->organization($request);

        abort_unless($vendorType->organization_id === $organization->id, 404);

        $vendorType->update($request->validated());

        return redirect()->route('setup.vendor-types');
    }

    public function continue(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.artist-types');
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.artist-types');
    }
}
