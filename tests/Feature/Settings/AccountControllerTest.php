<?php

namespace Tests\Feature\Settings;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            fn (Assert $page) => $page
                ->component('Settings/Account')
                ->where('account.name', $user->name)
                ->where('account.email', $user->email)
                ->where('account.phone', null),
        );
    }

    public function test_authenticated_user_can_update_account_details(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => 'Avery Festival',
            'email' => 'avery@example.test',
            'phone' => '+1 604 555 0142',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Avery Festival',
            'email' => 'avery@example.test',
            'phone' => '+1 604 555 0142',
        ]);
    }

    public function test_account_email_must_be_unique(): void
    {
        $user = $this->userWithCompletedSetup();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name,
            'email' => $otherUser->email,
            'phone' => '',
        ])->assertSessionHasErrors('email');
    }

    public function test_account_renders_and_saves_custom_field_values(): void
    {
        $user = $this->userWithCompletedSetup();
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_USER,
            'label' => 'Wristband',
            'key' => 'wristband',
            'type' => 'select',
            'options' => ['Gold', 'General admission'],
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->get(route('settings.account'))->assertInertia(
            fn (Assert $page) => $page
                ->where('customFields.0.id', $field->id)
                ->where('customFields.0.value', null),
        );

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'custom_fields' => [$field->id => 'Gold'],
        ])->assertRedirect();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'custom_fieldable_type' => User::class,
            'custom_fieldable_id' => $user->id,
            'value_text' => 'Gold',
            'value_search' => 'gold',
        ]);
    }

    public function test_account_rejects_an_unknown_custom_field_choice(): void
    {
        $user = $this->userWithCompletedSetup();
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_USER,
            'label' => 'Wristband',
            'key' => 'wristband',
            'type' => 'select',
            'options' => ['Gold'],
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'custom_fields' => [$field->id => 'Counterfeit'],
        ])->assertSessionHasErrors("custom_fields.{$field->id}");
    }

    public function test_authenticated_user_can_update_password_with_current_password(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->put(route('settings.account.password.update'), [
            'current_password' => 'password',
            'password' => 'a-secure-new-password',
            'password_confirmation' => 'a-secure-new-password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('a-secure-new-password', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->put(route('settings.account.password.update'), [
            'current_password' => 'not-the-current-password',
            'password' => 'a-secure-new-password',
            'password_confirmation' => 'a-secure-new-password',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_unauthenticated_account_settings_request_redirects_to_login(): void
    {
        $this->get(route('settings.account'))->assertRedirect(route('login'));
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

    public function test_user_custom_fields_stay_global_across_events(): void
    {
        $user = $this->userWithCompletedSetup();
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_USER,
            'label' => 'Shirt size',
            'key' => 'shirt_size',
            'type' => 'text',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'custom_fields' => [$field->id => 'M'],
        ])->assertRedirect();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'custom_fieldable_type' => User::class,
            'custom_fieldable_id' => $user->id,
            'event_id' => null,
            'value_text' => 'M',
        ]);
        $this->assertSame(1, $user->customFieldValues()->count());
    }
}
