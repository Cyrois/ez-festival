<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadyController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response
    {
        $organization = $this->organization();

        return Inertia::render('Setup/Ready', [
            'organization' => [
                'name' => $organization->name(),
            ],
            'currentStep' => 5,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->ensureOrganization();

        $this->organization()->markSetupComplete();

        return redirect()->route('dashboard');
    }
}
