<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealReportResource;
use App\Http\Responses\MealReportCsvResponse;
use App\Queries\MealReportQuery;
use App\Support\EventContext;
use Illuminate\Http\Request;

class MealReportExportController extends Controller
{
    public function index(Request $request, EventContext $context, MealReportQuery $meals): MealReportCsvResponse
    {
        $event = $context->requireCurrent($request->user());
        $report = (new MealReportResource($meals->rows($event)))->resolve($request);

        return new MealReportCsvResponse($event->name, $report['rows']);
    }
}
