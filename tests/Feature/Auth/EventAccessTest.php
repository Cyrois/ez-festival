<?php

namespace Tests\Feature\Auth;

use App\Models\Event;
use App\Models\PassType;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_non_admin_can_only_open_events_with_an_active_role(): void
    {
        $first = $this->event('Allowed Festival');
        $second = $this->event('Hidden Festival');
        $user = $this->userWithAccessTo($first);

        $this->actingAs($user)->get(route('events.show', $first))->assertOk();
        $this->get(route('events.show', $second))->assertNotFound();
        $this->post(route('settings.events.set-primary', $second))->assertNotFound();
    }

    public function test_inaccessible_saved_and_default_events_fall_back_to_an_accessible_event(): void
    {
        $allowed = $this->event('Allowed Festival');
        $hidden = $this->event('Hidden Festival');
        $user = $this->userWithAccessTo($allowed);
        $user->forceFill(['current_event_id' => $hidden->id])->save();
        app(OrganizationContext::class)->setDefaultEvent($hidden);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(
            fn (Assert $page) => $page->where('event.id', $allowed->id),
        );
    }

    public function test_seeded_administrator_role_name_has_no_special_access(): void
    {
        $first = $this->event('Role Festival');
        $second = $this->event('Other Festival');
        $role = Role::query()->create(['name' => 'Administrator']);
        $user = $this->userWithAccessTo($first, $role);

        $this->assertFalse($user->isAdmin());
        $this->actingAs($user)->get(route('events.show', $first))->assertOk();
        $this->get(route('events.show', $second))->assertNotFound();
        $this->get(route('settings.team.show', $user->person))->assertInertia(
            fn (Assert $page) => $page->where('viewerCanManageAdmin', false),
        );
    }

    public function test_role_changes_take_effect_on_the_next_request_without_signing_out(): void
    {
        $first = $this->event('First Festival');
        $second = $this->event('Second Festival');
        $role = Role::query()->create(['name' => 'Staff']);
        $user = $this->userWithAccessTo($first, $role);
        $this->giveAccess($user, $second, $role);

        $this->actingAs($user)->get(route('events.show', $first))->assertOk();

        TeamEngagement::query()
            ->where('person_id', $user->person_id)
            ->where('event_id', $first->id)
            ->update(['role_id' => null]);

        $this->get(route('events.show', $first))->assertNotFound();
        $this->get(route('events.show', $second))->assertOk();

        $role->update(['active' => false]);

        $this->get(route('events.show', $second))->assertForbidden();

        $role->update(['active' => true]);

        $this->get(route('events.show', $second))->assertOk();
    }

    public function test_turning_login_off_refuses_the_next_request_for_non_admin_and_admin(): void
    {
        $event = $this->event('Festival');
        $user = $this->userWithAccessTo($event);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $user->person->update(['can_log_in' => false]);
        $this->get(route('dashboard'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $admin->setCurrentEvent($event);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $admin->person->update(['can_log_in' => false]);
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_admin_can_open_every_event_without_a_role(): void
    {
        $first = $this->event('First Festival');
        $second = $this->event('Second Festival');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $admin->setCurrentEvent($first);
        $this->completeSetup($first);

        $this->actingAs($admin)->get(route('events.show', $first))->assertOk();
        $this->get(route('events.show', $second))->assertOk();
    }

    public function test_route_cannot_mix_resources_from_different_events(): void
    {
        $first = $this->event('First Festival');
        $second = $this->event('Second Festival');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $admin->setCurrentEvent($first);
        $this->completeSetup($first);
        $passType = PassType::query()->create([
            'event_id' => $second->id,
            'name' => 'Other event pass',
        ]);

        $this->actingAs($admin)->put(
            route('credentials.passes.update', [$first, $passType]),
            ['name' => 'Forged update'],
        )->assertNotFound();

        $this->assertSame('Other event pass', $passType->fresh()->name);
    }

    private function userWithAccessTo(Event $event, ?Role $role = null): User
    {
        $user = User::factory()->create();
        $role ??= Role::query()->create(['name' => 'Staff']);
        $this->giveAccess($user, $event, $role);
        $user->setCurrentEvent($event);

        $this->completeSetup($event);

        return $user;
    }

    private function giveAccess(User $user, Event $event, Role $role): void
    {
        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
    }

    private function completeSetup(Event $event): void
    {
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
    }

    private function event(string $name): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }
}
