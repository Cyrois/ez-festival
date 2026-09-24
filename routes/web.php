<?php

use App\Http\Controllers\ArtistCheckInController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\ArtistEngagementPersonController;
use App\Http\Controllers\ArtistPassAssignmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Credentials\EntitlementItemController;
use App\Http\Controllers\Credentials\PassTypeController;
use App\Http\Controllers\Credentials\ProductsController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PassAssignmentController;
use App\Http\Controllers\PatronController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\ArtistTypeController as SettingsArtistTypeController;
use App\Http\Controllers\Settings\CustomFieldController;
use App\Http\Controllers\Settings\EventController as SettingsEventController;
use App\Http\Controllers\Settings\EventLocationController;
use App\Http\Controllers\Settings\FeatureFlagController;
use App\Http\Controllers\Settings\LabelController;
use App\Http\Controllers\Settings\PrimaryEventSettingsController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\VendorTypeController as SettingsVendorTypeController;
use App\Http\Controllers\Setup\ArtistTypeController;
use App\Http\Controllers\Setup\EventController as SetupEventController;
use App\Http\Controllers\Setup\LocationController;
use App\Http\Controllers\Setup\ReadyController;
use App\Http\Controllers\Setup\VendorTypeController;
use App\Http\Controllers\UiKitController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorEngagementPersonController;
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

    if (app()->environment('local') || config('app.debug')) {
        Route::get('ui', UiKitController::class)->name('ui');
    }

    Route::middleware(['organization', 'setup.complete'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('check-in', [ArtistCheckInController::class, 'index'])->name('check-in.index');
        Route::get('check-in/artists/{engagement}', [ArtistCheckInController::class, 'show'])->name('check-in.show');
        Route::post('check-in/expected-entitlements/{expectedEntitlement}/issues', [ArtistCheckInController::class, 'store'])
            ->middleware('event.writable')->name('check-in.issues.store');

        Route::get('artists/advancing', [ArtistController::class, 'index'])->name('artists.index');
        Route::redirect('artists/check-in', '/check-in')->name('artists.check-in');
        Route::redirect('artists/check-in/{engagement}', '/check-in/artists/{engagement}')->name('artists.check-in.show');
        Route::post('artists/check-in/expected-entitlements/{expectedEntitlement}/issues', [ArtistCheckInController::class, 'store'])
            ->middleware('event.writable')->name('artists.check-in.issues.store');
        Route::get('artists/create', [ArtistController::class, 'create'])->name('artists.create');
        Route::get('artists/engagements/{engagement}', [ArtistController::class, 'view'])->name('artists.view');
        Route::put('artists/engagements/{engagement}', [ArtistController::class, 'update'])
            ->middleware('event.writable')->name('artists.update');
        Route::post('artists/engagements/{engagement}/notes', [ArtistController::class, 'storeNote'])
            ->middleware('event.writable')->name('artists.notes.store');
        Route::post('artists/engagements/{engagement}/people', [ArtistEngagementPersonController::class, 'store'])
            ->middleware('event.writable')->name('artists.people.store');
        Route::put('artists/engagements/{engagement}/people/{person}', [ArtistEngagementPersonController::class, 'update'])
            ->middleware('event.writable')->name('artists.people.update');
        Route::delete('artists/engagements/{engagement}/people/{person}', [ArtistEngagementPersonController::class, 'destroy'])
            ->middleware('event.writable')->name('artists.people.destroy');
        Route::post('artists/engagements/{engagement}/pass-assignments', [ArtistPassAssignmentController::class, 'store'])
            ->middleware('event.writable')->name('artists.pass-assignments.store');
        Route::post('events/{event}/artists', [ArtistController::class, 'store'])
            ->middleware('event.writable')->name('artists.store');

        Route::get('patrons', [PatronController::class, 'index'])
            ->middleware('feature:patrons')
            ->name('patrons.index');
        Route::get('crew', [CrewController::class, 'index'])
            ->middleware('feature:crew')
            ->name('crew.index');

        Route::get('credentials/passes', [PassTypeController::class, 'index'])
            ->name('credentials.passes');
        Route::get('credentials/products', [ProductsController::class, 'index'])
            ->name('credentials.products');
        Route::get('credentials/passes/create', [PassTypeController::class, 'create'])
            ->name('credentials.passes.create');
        Route::get('credentials/passes/{passType}/edit', [PassTypeController::class, 'edit'])
            ->name('credentials.passes.edit');
        Route::post('events/{event}/credentials/passes', [PassTypeController::class, 'store'])
            ->middleware('event.writable')
            ->name('credentials.passes.store');
        Route::put('events/{event}/credentials/passes/{passType}', [PassTypeController::class, 'update'])
            ->middleware('event.writable')
            ->name('credentials.passes.update');
        Route::delete('events/{event}/credentials/passes/{passType}', [PassTypeController::class, 'destroy'])
            ->middleware('event.writable')
            ->name('credentials.passes.destroy');
        Route::get('credentials/entitlements', [EntitlementItemController::class, 'index'])
            ->name('credentials.entitlements');
        Route::get('credentials/entitlements/{entitlementItem}', [EntitlementItemController::class, 'show'])
            ->name('credentials.entitlements.show');
        Route::get('credentials/entitlements/{entitlementItem}/edit', [EntitlementItemController::class, 'edit'])
            ->name('credentials.entitlements.edit');
        Route::post('events/{event}/credentials/entitlements', [EntitlementItemController::class, 'store'])
            ->middleware('event.writable')
            ->name('credentials.entitlements.store');
        Route::put('events/{event}/credentials/entitlements/{entitlementItem}', [EntitlementItemController::class, 'update'])
            ->middleware('event.writable')
            ->name('credentials.entitlements.update');
        Route::post('events/{event}/credentials/entitlements/{entitlementItem}/adjustments', [EntitlementItemController::class, 'adjust'])
            ->middleware('event.writable')
            ->name('credentials.entitlements.adjustments.store');
        Route::delete('events/{event}/credentials/entitlements/{entitlementItem}', [EntitlementItemController::class, 'destroy'])
            ->middleware('event.writable')
            ->name('credentials.entitlements.destroy');

        Route::redirect('vendors', '/vendors/advancing')->name('vendors.index');
        Route::get('vendors/advancing', [VendorController::class, 'index'])->name('vendors.advancing');
        Route::redirect('vendors/check-in', '/check-in?type=vendor')->name('vendors.check-in');
        Route::get('vendors/engagements/{engagement}', [VendorController::class, 'view'])->name('vendors.view');
        Route::put('vendors/engagements/{engagement}', [VendorController::class, 'update'])
            ->middleware('event.writable')->name('vendors.update');
        Route::post('vendors/engagements/{engagement}/people', [VendorEngagementPersonController::class, 'store'])
            ->middleware('event.writable')->name('vendors.people.store');
        Route::put('vendors/engagements/{engagement}/people/{person}', [VendorEngagementPersonController::class, 'update'])
            ->middleware('event.writable')->name('vendors.people.update');
        Route::delete('vendors/engagements/{engagement}/people/{person}', [VendorEngagementPersonController::class, 'destroy'])
            ->middleware('event.writable')->name('vendors.people.destroy');
        Route::get('vendors/create', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('events/{event}/vendors', [VendorController::class, 'store'])
            ->middleware('event.writable')->name('vendors.store');

        Route::put('pass-assignments/{assignment}', [PassAssignmentController::class, 'update'])
            ->middleware('event.writable')->name('pass-assignments.update');
        Route::delete('pass-assignments/{assignment}', [PassAssignmentController::class, 'destroy'])
            ->middleware('event.writable')->name('pass-assignments.destroy');

        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::post('events/{event}/lock', [EventController::class, 'lock'])->name('events.lock');
        Route::post('events/{event}/unlock', [EventController::class, 'unlock'])->name('events.unlock');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('events', [SettingsEventController::class, 'index'])->name('events.index');
            Route::get('events/create', [SettingsEventController::class, 'create'])->name('events.create');
            Route::post('events', [SettingsEventController::class, 'store'])->name('events.store');
            Route::get('locations', [PrimaryEventSettingsController::class, 'locations'])->name('locations');
            Route::get('roles', [PrimaryEventSettingsController::class, 'roles'])->name('roles');
            Route::get('users', [PrimaryEventSettingsController::class, 'users'])->name('users');
            Route::get('artist-types', [SettingsArtistTypeController::class, 'index'])->name('artist-types');
            Route::post('artist-types', [SettingsArtistTypeController::class, 'store'])->name('artist-types.store');
            Route::put('artist-types/{artistType}', [SettingsArtistTypeController::class, 'update'])->name('artist-types.update');
            Route::delete('artist-types/{artistType}', [SettingsArtistTypeController::class, 'destroy'])->name('artist-types.destroy');
            Route::post('artist-types/reorder', [SettingsArtistTypeController::class, 'reorder'])->name('artist-types.reorder');
            Route::get('vendor-types', [SettingsVendorTypeController::class, 'index'])->name('vendor-types');
            Route::post('vendor-types', [SettingsVendorTypeController::class, 'store'])->name('vendor-types.store');
            Route::put('vendor-types/{vendorType}', [SettingsVendorTypeController::class, 'update'])->name('vendor-types.update');
            Route::delete('vendor-types/{vendorType}', [SettingsVendorTypeController::class, 'destroy'])->name('vendor-types.destroy');
            Route::post('vendor-types/reorder', [SettingsVendorTypeController::class, 'reorder'])->name('vendor-types.reorder');
            Route::get('team', TeamController::class)->name('team');
            Route::get('account', [AccountController::class, 'index'])->name('account');
            Route::put('account', [AccountController::class, 'update'])->name('account.update');
            Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
            Route::get('custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields');
            Route::post('custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
            Route::put('custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
            Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');
            Route::get('feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags');
            Route::put('feature-flags/{flag}', [FeatureFlagController::class, 'update'])->name('feature-flags.update');
            Route::get('labels', LabelController::class)->name('labels');
            Route::get('events/{event}/edit', [SettingsEventController::class, 'edit'])->name('events.edit');
            Route::post('events/{event}/set-primary', [SettingsEventController::class, 'setPrimary'])->name('events.set-primary');
            Route::get('events/{event}/roles', [SettingsEventController::class, 'roles'])->name('events.roles');
            Route::get('events/{event}/users', [SettingsEventController::class, 'users'])->name('events.users');

            Route::get('events/{event}/locations', [EventLocationController::class, 'index'])->name('events.locations');

            Route::middleware('event.writable')->group(function () {
                Route::put('events/{event}', [SettingsEventController::class, 'update'])->name('events.update');
                Route::delete('events/{event}', [SettingsEventController::class, 'destroy'])->name('events.destroy');
                Route::post('events/{event}/locations', [EventLocationController::class, 'store'])->name('events.locations.store');
                Route::put('events/{event}/locations/{location}', [EventLocationController::class, 'update'])->name('events.locations.update');
                Route::delete('events/{event}/locations/{location}', [EventLocationController::class, 'destroy'])->name('events.locations.destroy');
            });
        });
    });

    Route::middleware('organization')->prefix('setup')->name('setup.')->group(function () {
        // Event-scoped writes: blocked when active event is locked or non-active context.
        Route::middleware('event.writable')->group(function () {
            Route::get('event', [SetupEventController::class, 'show'])->name('event');
            Route::post('event', [SetupEventController::class, 'store']);

            Route::get('locations', [LocationController::class, 'show'])->name('locations');
            Route::post('locations', [LocationController::class, 'store']);
            Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
            Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
            Route::post('locations/continue', [LocationController::class, 'continue'])->name('locations.continue');
            Route::post('locations/skip', [LocationController::class, 'skip'])->name('locations.skip');
        });

        // Organization-scoped setup (not event writes).
        Route::get('vendor-types', [VendorTypeController::class, 'show'])->name('vendor-types');
        Route::post('vendor-types', [VendorTypeController::class, 'store']);
        Route::put('vendor-types/{vendorType}', [VendorTypeController::class, 'update'])->name('vendor-types.update');
        Route::post('vendor-types/continue', [VendorTypeController::class, 'continue'])->name('vendor-types.continue');
        Route::post('vendor-types/skip', [VendorTypeController::class, 'skip'])->name('vendor-types.skip');

        Route::get('artist-types', [ArtistTypeController::class, 'show'])->name('artist-types');
        Route::post('artist-types', [ArtistTypeController::class, 'store']);
        Route::put('artist-types/{artistType}', [ArtistTypeController::class, 'update'])->name('artist-types.update');
        Route::delete('artist-types/{artistType}', [ArtistTypeController::class, 'destroy'])->name('artist-types.destroy');
        Route::post('artist-types/continue', [ArtistTypeController::class, 'continue'])->name('artist-types.continue');
        Route::post('artist-types/skip', [ArtistTypeController::class, 'skip'])->name('artist-types.skip');

        Route::get('ready', [ReadyController::class, 'show'])->name('ready');
        Route::post('ready', [ReadyController::class, 'complete'])->name('ready.complete');
    });
});
