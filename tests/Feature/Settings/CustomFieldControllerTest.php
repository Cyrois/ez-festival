<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomFieldControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_custom_fields_settings_renders_for_an_authenticated_user_with_completed_setup(): void
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

        $this->actingAs($user)->get(route('settings.custom-fields'))->assertInertia(
            fn (Assert $page) => $page->component('Settings/CustomFields'),
        );
    }

    public function test_unauthenticated_custom_fields_settings_request_redirects_to_login(): void
    {
        $this->get(route('settings.custom-fields'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_a_user_custom_field(): void
    {
        $user = $this->userWithCompletedSetup();
        $organization = Organization::query()->firstOrFail();

        $this->actingAs($user)->post(route('settings.custom-fields.store'), [
            'target' => 'user',
            'label' => 'Wristband',
            'type' => 'select',
            'required' => true,
            'options' => ['Gold', 'General admission'],
        ])->assertRedirect();

        $this->assertDatabaseHas('custom_fields', [
            'organization_id' => $organization->id,
            'target' => 'user',
            'label' => 'Wristband',
            'key' => 'wristband',
            'type' => 'select',
            'required' => true,
            'active' => true,
        ]);
    }

    public function test_select_field_requires_at_least_one_choice(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->post(route('settings.custom-fields.store'), [
            'target' => 'user',
            'label' => 'Wristband',
            'type' => 'select',
            'options' => [],
        ])->assertSessionHasErrors('options');
    }

    public function test_authenticated_user_can_create_custom_fields_for_each_supported_target(): void
    {
        $user = $this->userWithCompletedSetup();

        foreach (['artist', 'vendor', 'patron', 'team_member', 'user'] as $target) {
            $this->actingAs($user)->post(route('settings.custom-fields.store'), [
                'target' => $target,
                'label' => "{$target} identifier",
                'type' => 'text',
            ])->assertRedirect();

            $this->assertDatabaseHas('custom_fields', [
                'target' => $target,
                'label' => "{$target} identifier",
            ]);
        }
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
