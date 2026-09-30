<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GenerateGlobalTeamPasswordRequest;
use App\Http\Resources\TemporaryPasswordResource;
use App\Models\Person;
use App\Services\LoginInvitationService;

class TeamTemporaryPasswordController extends Controller
{
    public function __construct(private readonly LoginInvitationService $invitations) {}

    public function store(
        GenerateGlobalTeamPasswordRequest $request,
        Person $person,
    ): TemporaryPasswordResource {
        return new TemporaryPasswordResource(
            $this->invitations->generateTemporaryPassword($person),
        );
    }
}
