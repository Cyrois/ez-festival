<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Vendors/Index', [
            'event' => request()->user()->effectiveEvent()?->only('id', 'name', 'locked'),
        ]);
    }
}
