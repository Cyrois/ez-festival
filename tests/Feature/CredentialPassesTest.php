<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CredentialPassesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('credentials.passes'))->assertRedirect(route('login'));
    }

    public function test_passes_page_renders_for_a_user_with_completed_setup(): void
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Passes')
                ->where('activeEvent.id', $event->id)
                ->where('activeEvent.name', 'Sunrise Folk Fest 2026'),
        );
    }
}
