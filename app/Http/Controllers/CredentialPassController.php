<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CredentialPassController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('view-credentials');

        return Inertia::render('Credentials/Passes');
    }
}
