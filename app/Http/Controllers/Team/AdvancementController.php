<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdvancementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('view-team');

        return Inertia::render('Team/Advancement');
    }
}
