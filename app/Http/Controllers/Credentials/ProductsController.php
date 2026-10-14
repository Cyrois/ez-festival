<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('view-credentials');

        return Inertia::render('Credentials/Products');
    }
}
