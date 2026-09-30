<?php

namespace Tests\Feature\Auth;

use App\Models\Event;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_with_correct_credentials_and_no_role_cannot_sign_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => __('auth.no_event_access'),
        ]);

        $this->assertGuest();
    }

    public function test_wrong_password_keeps_the_usual_credentials_error(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => __('These credentials do not match our records.'),
        ]);

        $this->assertGuest();
    }

    public function test_non_admin_with_only_turned_off_roles_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $role = Role::query()->create(['name' => 'Historical role', 'active' => false]);
        $this->engagement($user, $event, $role);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => __('auth.no_event_access')]);

        $this->assertGuest();
    }

    public function test_non_admin_with_an_active_role_but_login_turned_off_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $user->person->update(['can_log_in' => false]);
        $event = $this->event();
        $role = Role::query()->create(['name' => 'Staff']);
        $this->engagement($user, $event, $role);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => __('auth.no_event_access')]);

        $this->assertGuest();
    }

    public function test_non_admin_with_an_active_role_and_login_enabled_can_sign_in(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $role = Role::query()->create(['name' => 'Staff']);
        $this->engagement($user, $event, $role);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/setup/event');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_without_any_event_role_can_sign_in(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/setup/event');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_with_login_turned_off_cannot_sign_in(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $admin->person->update(['can_log_in' => false]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => __('auth.no_event_access')]);

        $this->assertGuest();
    }

    private function event(): Event
    {
        return Event::query()->create([
            'name' => 'Festival',
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }

    private function engagement(User $user, Event $event, Role $role): TeamEngagement
    {
        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
    }
}
