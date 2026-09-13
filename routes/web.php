<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Setup\ArtistTypeController;
use App\Http\Controllers\Setup\EventController;
use App\Http\Controllers\Setup\LocationController;
use App\Http\Controllers\Setup\ReadyController;
use App\Http\Controllers\Setup\VendorTypeController;
use App\Support\PostLoginRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->to(PostLoginRedirect::for(auth()->user()));
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware(['organization', 'setup.complete'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

    Route::middleware('organization')->prefix('setup')->name('setup.')->group(function () {
        Route::get('event', [EventController::class, 'show'])->name('event');
        Route::post('event', [EventController::class, 'store']);
        Route::post('event/skip', [EventController::class, 'skip'])->name('event.skip');

        Route::get('locations', [LocationController::class, 'show'])->name('locations');
        Route::post('locations', [LocationController::class, 'store']);
        Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::post('locations/continue', [LocationController::class, 'continue'])->name('locations.continue');
        Route::post('locations/skip', [LocationController::class, 'skip'])->name('locations.skip');

        Route::get('vendor-types', [VendorTypeController::class, 'show'])->name('vendor-types');
        Route::post('vendor-types', [VendorTypeController::class, 'store']);
        Route::put('vendor-types/{vendorType}', [VendorTypeController::class, 'update'])->name('vendor-types.update');
        Route::post('vendor-types/continue', [VendorTypeController::class, 'continue'])->name('vendor-types.continue');
        Route::post('vendor-types/skip', [VendorTypeController::class, 'skip'])->name('vendor-types.skip');

        Route::get('artist-types', [ArtistTypeController::class, 'show'])->name('artist-types');
        Route::post('artist-types', [ArtistTypeController::class, 'store']);
        Route::put('artist-types/{artistType}', [ArtistTypeController::class, 'update'])->name('artist-types.update');
        Route::post('artist-types/continue', [ArtistTypeController::class, 'continue'])->name('artist-types.continue');
        Route::post('artist-types/skip', [ArtistTypeController::class, 'skip'])->name('artist-types.skip');

        Route::get('ready', [ReadyController::class, 'show'])->name('ready');
        Route::post('ready', [ReadyController::class, 'complete'])->name('ready.complete');
    });
});
