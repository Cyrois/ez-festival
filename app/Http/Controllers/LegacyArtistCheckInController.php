<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\ViewCheckInRequest;
use App\Models\ArtistEngagement;
use Illuminate\Http\RedirectResponse;

class LegacyArtistCheckInController extends Controller
{
    public function show(ViewCheckInRequest $request, ArtistEngagement $engagement): RedirectResponse
    {
        return redirect()->route('check-in.show', [
            'engagement' => $engagement,
            ...$request->safe()->only('person'),
        ]);
    }
}
