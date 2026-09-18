<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_account_settings_renders_for_an_authenticated_user_with_completed_setup(): void
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Festival',
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        $this->actingAs($user)->get(route('settings.account'))->assertInertia(
            fn (Assert $page) => $page->component('Settings/Account'),
        );
    }

    public function test_unauthenticated_account_settings_request_redirects_to_login(): void
    {
        $this->get(route('settings.account'))->assertRedirect(route('login'));
    }
}
