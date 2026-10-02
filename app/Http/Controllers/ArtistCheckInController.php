<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Http\Resources\ArtistCheckInShowResource;
use App\Models\ArtistEngagement;
use App\Services\CheckInPageService;
use Inertia\Response;

class ArtistCheckInController extends Controller
{
    public function show(ViewCheckInRequest $request, ArtistEngagement $engagement, CheckInPageService $page): Response
    {
        return $page->show($request, $engagement, new ArtistCheckInShowResource($engagement), 'artist');
    }
}
