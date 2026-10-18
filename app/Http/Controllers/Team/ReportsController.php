<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamReportsRequest;
use App\Support\EventContext;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(IndexTeamReportsRequest $request, EventContext $eventContext): Response
    {
        $eventContext->requireCurrent($request->user());

        return Inertia::render('Team/Reports');
    }
}
