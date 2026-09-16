<?php

namespace App\Http\Controllers;

use App\Models\VendorType;
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

    public function create(): Response
    {
        $event = request()->user()->effectiveEvent();
        abort_if($event === null, 404);
        $event->ensureWritable();

        return Inertia::render('Vendors/Create', [
            'event' => $event->only('id', 'name'),
            'types' => VendorType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
