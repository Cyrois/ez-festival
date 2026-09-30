<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeTemporaryPasswordRequest;
use App\Http\Requests\Settings\GenerateGlobalTeamPasswordRequest;
use App\Http\Resources\TemporaryPasswordResource;
use App\Models\Person;
use App\Services\AccountService;
use App\Services\LoginInvitationService;
use App\Support\PostLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TemporaryPasswordController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly LoginInvitationService $invitations,
    ) {}

    public function store(
        GenerateGlobalTeamPasswordRequest $request,
        Person $person,
    ): TemporaryPasswordResource {
        return new TemporaryPasswordResource(
            $this->invitations->generateTemporaryPassword($person),
        );
    }

    public function edit(): Response
    {
        return Inertia::render('Auth/ChangeTemporaryPassword');
    }

    public function update(ChangeTemporaryPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->accounts->updatePassword($user, $request->validated('password'));

        return redirect()->to(PostLoginRedirect::for($user));
    }
}
