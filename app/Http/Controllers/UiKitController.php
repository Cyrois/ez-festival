<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class UiKitController extends Controller
{
    /**
     * Tiny in-app gallery for Designer visual pass on UI kit primitives.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Ui/Index');
    }
}
