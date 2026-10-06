<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Support\EventContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function index(Request $request, EventContext $context): Response
    {
        $context->requireCurrent($request->user());

        return Inertia::render('Kitchen/Index');
    }
}
