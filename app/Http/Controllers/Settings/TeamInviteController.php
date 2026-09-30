<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResendGlobalTeamInviteRequest;
use App\Models\Person;
use App\Services\LoginInvitationService;
use Illuminate\Http\RedirectResponse;

class TeamInviteController extends Controller
{
    public function __construct(private readonly LoginInvitationService $invitations) {}

    public function store(ResendGlobalTeamInviteRequest $request, Person $person): RedirectResponse
    {
        $this->invitations->send($person, $request->user());

        return back()->with('success', __('settings.team.toast.invite_resent'));
    }
}
