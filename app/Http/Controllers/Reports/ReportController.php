<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealReportResource;
use App\Queries\MealReportQuery;
use App\Support\EventContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, EventContext $context, MealReportQuery $meals): Response
    {
        $event = $context->requireCurrent($request->user());

        return Inertia::render('Reports/Index', [
            'meals' => (new MealReportResource($meals->rows($event)))->resolve($request),
        ]);
    }
}
