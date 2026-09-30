<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetInvitedPasswordRequest;
use App\Services\LoginInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitePasswordController extends Controller
{
    public function __construct(private readonly LoginInvitationService $invitations) {}

    public function show(Request $request, string $token): Response
    {
        $authenticated = $request->user() !== null;

        return Inertia::render('Auth/SetInvitedPassword', [
            'token' => $token,
            'authenticated' => $authenticated,
            'valid' => ! $authenticated && $this->invitations->isValid($token),
        ]);
    }

    public function update(SetInvitedPasswordRequest $request, string $token): RedirectResponse
    {
        if (! $this->invitations->setPassword($token, $request->validated('password'))) {
            return redirect()->route('team-invitations.show', $token);
        }

        return redirect()->route('login')
            ->with('success', __('auth.invitation.password_set'));
    }
}
