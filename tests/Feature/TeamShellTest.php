<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamShellTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_team_sections_require_authentication(): void
    {
        foreach (['advancement', 'scheduling', 'forms', 'configure'] as $section) {
            $this->get(route("team.{$section}"))->assertRedirect(route('login'));
        }
    }

    public function test_team_sections_render_their_stub_pages(): void
    {
        $user = $this->userWithCompletedSetup();

        foreach ([
            'advancement' => 'Team/Advancement',
            'scheduling' => 'Team/Scheduling',
            'forms' => 'Team/Forms',
            'configure' => 'Team/Configure',
        ] as $section => $component) {
            $this->actingAs($user)->get(route("team.{$section}"))->assertInertia(
                fn (Assert $page) => $page->component($component),
            );
        }
    }

    public function test_team_sections_use_the_team_permission(): void
    {
        $user = $this->userWithCompletedSetup();
        Gate::define('view-team', fn (): bool => false);

        $this->actingAs($user)->get(route('team.advancement'))->assertForbidden();
    }

    public function test_legacy_crew_route_is_not_available(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->get('/crew')->assertNotFound();
    }

    private function userWithCompletedSetup(): User
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

        return $user;
    }
}
