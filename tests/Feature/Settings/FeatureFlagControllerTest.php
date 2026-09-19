<?php

namespace Tests\Feature\Settings;

use App\Models\AppConfig;
use App\Models\Event;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeatureFlagControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registered_flags_use_their_code_defaults_without_database_rows(): void
    {
        $featureFlags = app(FeatureFlagService::class);

        $this->assertTrue($featureFlags->enabled('patrons'));
        $this->assertTrue($featureFlags->enabled('crew'));
        $this->assertDatabaseCount('app_config', 0);
    }

    public function test_a_database_override_is_used_and_records_the_actor(): void
    {
        $user = User::factory()->create();

        app(FeatureFlagService::class)->set('patrons', false, $user);

        $this->assertFalse(app(FeatureFlagService::class)->enabled('patrons'));
        $this->assertDatabaseHas('app_config', [
            'key' => 'patrons',
            'enabled' => false,
            'updated_by_user_id' => $user->id,
        ]);
    }

    public function test_feature_flag_settings_render_for_a_user_with_completed_setup(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->get(route('settings.feature-flags'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/FeatureFlags')
                ->has('flags', 2)
                ->where('flags.0.key', 'patrons')
                ->where('flags.0.enabled', true),
        );
    }

    public function test_an_authenticated_user_can_update_a_registered_feature_flag(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->put(route('settings.feature-flags.update', 'crew'), [
            'enabled' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_config', [
            'key' => 'crew',
            'enabled' => false,
            'updated_by_user_id' => $user->id,
        ]);
    }

    public function test_unknown_feature_flags_cannot_be_updated(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->put(route('settings.feature-flags.update', 'unknown'), [
            'enabled' => true,
        ])->assertNotFound();

        $this->assertDatabaseCount('app_config', 0);
    }

    public function test_disabled_feature_routes_return_not_found(): void
    {
        $user = $this->userWithCompletedSetup();
        AppConfig::query()->create([
            'key' => 'patrons',
            'enabled' => false,
        ]);
        app(FeatureFlagService::class)->set('crew', true, $user);

        $this->actingAs($user)->get(route('patrons.index'))->assertNotFound();
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
