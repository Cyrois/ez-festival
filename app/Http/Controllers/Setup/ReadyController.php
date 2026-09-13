<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadyController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('Setup/Ready', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'currentStep' => 5,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $organization->markSetupComplete();

        return redirect()->route('dashboard');
    }
}
