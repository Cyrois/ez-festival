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
        $client = $this->client();

        return Inertia::render('Setup/Ready', [
            'client' => [
                'name' => $client->name(),
            ],
            'currentStep' => 5,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $this->client()->markSetupComplete();

        return redirect()->route('dashboard');
    }
}
