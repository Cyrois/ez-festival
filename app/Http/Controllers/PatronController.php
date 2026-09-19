<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PatronController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Patrons/Index');
    }
}
