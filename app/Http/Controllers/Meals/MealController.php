<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Support\EventContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function index(Request $request, EventContext $context): Response
    {
        $context->requireCurrent($request->user());

        return Inertia::render('Meals/Index');
    }
}
