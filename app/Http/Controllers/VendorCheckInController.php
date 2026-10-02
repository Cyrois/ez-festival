<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Http\Resources\VendorCheckInShowResource;
use App\Models\VendorEngagement;
use App\Services\CheckInPageService;
use Inertia\Response;

class VendorCheckInController extends Controller
{
    public function show(ViewCheckInRequest $request, VendorEngagement $engagement, CheckInPageService $page): Response
    {
        return $page->show($request, $engagement, new VendorCheckInShowResource($engagement), 'vendor');
    }
}
