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
        if (! $this->invitations->send($person, $request->user())) {
            return back()
                ->with('warning', __('settings.team.toast.invite_failed'))
                ->with('warning_title', __('toast.warning_title'));
        }

        return back()->with('success', __('settings.team.toast.invite_resent'));
    }
}
