<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Http\Resources\TeamCheckInShowResource;
use App\Models\TeamEngagement;
use App\Services\CheckInPageService;
use Inertia\Response;

class TeamCheckInController extends Controller
{
    public function show(ViewCheckInRequest $request, TeamEngagement $engagement, CheckInPageService $page): Response
    {
        return $page->show($request, $engagement, new TeamCheckInShowResource($engagement), 'person');
    }
}
