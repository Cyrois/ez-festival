<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Support\EventContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealSettingsController extends Controller
{
    public function index(Request $request, EventContext $context): Response
    {
        $event = $context->requireCurrent($request->user());

        return Inertia::render('Meals/Settings', [
            'event' => ['id' => $event->id, 'name' => $event->name, 'is_locked' => $event->isLocked()],
            'canEdit' => $request->user()->can('meals.edit', $event),
        ]);
    }
}
