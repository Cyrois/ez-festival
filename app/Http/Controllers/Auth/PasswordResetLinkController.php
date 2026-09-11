<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view (stub).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }
}
