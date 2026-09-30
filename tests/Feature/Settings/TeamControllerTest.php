<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_team_settings_renders_for_an_authenticated_user_with_completed_setup(): void
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

        $this->actingAs($user)->get(route('settings.team'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/Team')
                ->where('hasAnyPeople', false),
        );
    }

    public function test_global_team_lists_only_people_with_a_role_and_off_roles_still_count(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $on = Role::query()->create(['name' => 'Staff']);
        $off = Role::query()->create(['name' => 'Box office', 'active' => false]);
        $listed = $this->engagement($event, 'Listed Person', $on);
        $historical = $this->engagement($event, 'Historical Person', $off);
        $this->engagement($event, 'No Access Person');
        Person::query()->create([
            'name' => 'Artist Contact',
            'email' => 'artist@example.test',
        ]);

        $this->actingAs($user)->get(route('settings.team'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/Team')
                ->where('hasAnyPeople', true),
        );

        $this->actingAs($user)
            ->getJson(route('settings.team.data', [
                'draw' => 4,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]))
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $historical->person_id)
            ->assertJsonPath('data.0.events.0.role_active', false)
            ->assertJsonPath('data.1.id', $listed->person_id);
    }

    public function test_global_team_data_table_filters_sorts_and_pages_on_the_server(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $this->engagement($event, 'Aster Person', $role);
        $matching = $this->engagement($event, 'Zinnia Person', $role);
        $this->engagement($event, 'No Access Person');

        $this->actingAs($user)
            ->getJson(route('settings.team.data', [
                'draw' => 9,
                'start' => 0,
                'length' => 1,
                'search' => ['value' => 'ZINNIA'],
                'order' => [['column' => 1, 'dir' => 'desc']],
            ]))
            ->assertOk()
            ->assertJsonPath('draw', 9)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->person_id);
    }

    public function test_global_team_list_and_person_page_show_login_access_state(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Login Person', $role);
        $engagement->person->update(['can_log_in' => true]);

        $this->actingAs($user)
            ->getJson(route('settings.team.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.can_log_in', true);

        $this->actingAs($user)->get(route('settings.team.show', $engagement->person))->assertInertia(
            fn (Assert $page) => $page
                ->where('person.can_log_in', true)
                ->where('person.login_disable_reason', null),
        );
    }

    public function test_global_team_data_table_rejects_unsupported_sort_columns(): void
    {
        [$user] = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->getJson(route('settings.team.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 2, 'dir' => 'asc']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order.0.column');
    }

    public function test_roles_count_each_person_once_across_events(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $second = $this->event('Second Festival');
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Shared Role Holder', $role);
        TeamEngagement::query()->create([
            'event_id' => $second->id,
            'person_id' => $engagement->person_id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);

        $this->actingAs($user)->get(route('settings.roles'))->assertInertia(
            fn (Assert $page) => $page->where('roles.data.0.people_count', 1),
        );
    }

    public function test_add_person_creates_volunteer_records_for_several_events_with_the_chosen_status(): void
    {
        Mail::fake();
        [$user, $event] = $this->userWithCompletedSetup();
        $second = $this->event('Winter Lights');
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $response = $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Ava Lee',
            'email' => ' AVA.LEE@EXAMPLE.TEST ',
            'phone' => null,
            'can_log_in' => true,
            'status' => 'reviewing',
            'event_access' => [
                ['event_id' => $event->id, 'role_id' => $role->id],
                ['event_id' => $second->id, 'role_id' => $role->id],
            ],
        ]);

        $person = Person::query()->where('email', 'ava.lee@example.test')->sole();
        $response->assertRedirect(route('settings.team.show', $person));
        $this->assertSame('ava.lee@example.test', $person->email);
        $this->assertNull($person->phone);
        $this->assertDatabaseCount('team_engagements', 2);
        $this->assertSame(
            ['volunteer'],
            TeamEngagement::query()->pluck('employment_type')->unique()->values()->all(),
        );
        $this->assertSame(
            ['reviewing'],
            TeamEngagement::query()->pluck('status')->unique()->values()->all(),
        );
        $this->assertTrue($person->can_log_in);
        Mail::assertNothingSent();
    }

    public function test_add_person_can_disable_login(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'No Login',
            'email' => 'no-login@example.test',
            'phone' => null,
            'can_log_in' => false,
            'status' => 'hired',
            'event_access' => [['event_id' => $event->id, 'role_id' => $role->id]],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Person::query()->where('email', 'no-login@example.test')->sole()->can_log_in);
    }

    public function test_add_person_keeps_login_on_when_reusing_a_person_with_a_login(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $knownUser = User::factory()->create();
        $knownUser->person->update(['can_log_in' => true]);

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => $knownUser->name,
            'email' => $knownUser->email,
            'phone' => null,
            'can_log_in' => true,
            'status' => 'hired',
            'event_access' => [['event_id' => $event->id, 'role_id' => $role->id]],
        ])->assertRedirect(route('settings.team.show', $knownUser->person));

        $this->assertTrue($knownUser->person->fresh()->can_log_in);
    }

    public function test_add_person_validates_required_access_and_refuses_locked_events_or_off_roles(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $event->lock();
        $off = Role::query()->create(['name' => 'Old role', 'active' => false]);
        $peopleBefore = Person::query()->count();

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => '',
            'email' => '',
            'can_log_in' => true,
            'status' => 'applied',
            'event_access' => [],
        ])->assertSessionHasErrors(['name', 'email', 'event_access']);

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Locked Person',
            'email' => 'locked@example.test',
            'can_log_in' => true,
            'status' => 'applied',
            'event_access' => [['event_id' => $event->id, 'role_id' => $off->id]],
        ])->assertSessionHasErrors([
            'event_access.0.event_id',
            'event_access.0.role_id',
        ]);

        $this->assertDatabaseCount('people', $peopleBefore);
    }

    public function test_add_person_blocks_an_existing_global_team_email_ignoring_case_and_spaces(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $existing = $this->engagement($event, 'Morgan West', $role);
        $peopleBefore = Person::query()->count();

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Different Name',
            'email' => '  MORGAN-WEST@EXAMPLE.TEST ',
            'can_log_in' => true,
            'status' => 'applied',
            'event_access' => [['event_id' => $event->id, 'role_id' => $role->id]],
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('people', $peopleBefore);
        $this->assertDatabaseCount('team_engagements', 1);
        $this->assertSame('Morgan West', $existing->person->fresh()->name);
    }

    public function test_add_person_reuses_a_known_contact_and_updates_an_existing_no_role_record(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Event manager']);
        $person = Person::query()->create([
            'name' => 'Stored Name',
            'email' => 'known@example.test',
            'phone' => 'stored phone',
        ]);
        $engagement = TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'status' => 'applied',
            'employment_type' => 'paid',
            'hourly_pay' => 25,
        ]);
        $peopleBefore = Person::query()->count();

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Typed Name',
            'email' => ' KNOWN@EXAMPLE.TEST ',
            'phone' => 'typed phone',
            'can_log_in' => true,
            'status' => 'hired',
            'event_access' => [['event_id' => $event->id, 'role_id' => $role->id]],
        ])->assertRedirect(route('settings.team.show', $person));

        $this->assertDatabaseCount('people', $peopleBefore);
        $this->assertSame('Stored Name', $person->fresh()->name);
        $this->assertSame('stored phone', $person->fresh()->phone);
        $engagement->refresh();
        $this->assertSame($role->id, $engagement->role_id);
        $this->assertSame('hired', $engagement->status);
        $this->assertSame('paid', $engagement->employment_type);
        $this->assertSame('25.00', $engagement->hourly_pay);
    }

    public function test_email_lookup_distinguishes_known_contacts_from_global_team_people(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $global = $this->engagement($event, 'Global Person', $role)->person;
        Person::query()->create(['name' => 'Known Contact', 'email' => 'known@example.test']);

        $this->actingAs($user)
            ->getJson(route('settings.team.lookup', ['email' => 'GLOBAL-PERSON@example.test']))
            ->assertOk()
            ->assertJsonPath('data.on_global_team', true)
            ->assertJsonPath('data.person.id', $global->id);

        $this->actingAs($user)
            ->getJson(route('settings.team.lookup', ['email' => ' known@example.test ']))
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.on_global_team', false)
            ->assertJsonMissingPath('data.person');
    }

    public function test_person_page_updates_profile_and_access_together_while_preserving_team_records(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $second = $this->event('Second Event');
        $firstRole = Role::query()->create(['name' => 'Staff']);
        $secondRole = Role::query()->create(['name' => 'Volunteer lead']);
        $engagement = $this->engagement($event, 'Jordan Lee', $firstRole);
        $engagement->update(['employment_type' => 'paid', 'hourly_pay' => 30]);

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => 'Jordan L.',
            'phone' => '555-0199',
            'can_log_in' => true,
            'event_access' => [
                ['event_id' => $event->id, 'role_id' => null, 'status' => null],
                ['event_id' => $second->id, 'role_id' => $secondRole->id, 'status' => 'hired'],
            ],
        ])->assertRedirect(route('settings.team.show', $engagement->person));

        $engagement->refresh();
        $this->assertNull($engagement->role_id);
        $this->assertSame('paid', $engagement->employment_type);
        $this->assertSame('30.00', $engagement->hourly_pay);
        $this->assertDatabaseHas('team_engagements', [
            'event_id' => $second->id,
            'person_id' => $engagement->person_id,
            'role_id' => $secondRole->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
        $this->assertSame('Jordan L.', $engagement->person->fresh()->name);
        $this->assertSame('555-0199', $engagement->person->fresh()->phone);
        $this->assertTrue($engagement->person->fresh()->can_log_in);
    }

    public function test_person_page_turns_login_off_without_changing_roles_or_admin_flag(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Switch Person', $role);
        $targetUser = User::factory()->create(['person_id' => $engagement->person_id]);
        $engagement->person->update(['can_log_in' => true]);

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => 'Switch Person',
            'phone' => null,
            'can_log_in' => false,
            'is_admin' => true,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $role->id,
                'status' => null,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($engagement->person->fresh()->can_log_in);
        $this->assertSame($role->id, $engagement->fresh()->role_id);
        $this->assertFalse($targetUser->fresh()->is_admin);
    }

    public function test_person_cannot_turn_off_their_own_login(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
        $user->person->update(['can_log_in' => true]);

        $this->actingAs($user)->get(route('settings.team.show', $user->person))->assertInertia(
            fn (Assert $page) => $page->where(
                'person.login_disable_reason',
                __('settings.team.login.own_disabled'),
            ),
        );

        $this->actingAs($user)->put(route('settings.team.update', $user->person), [
            'name' => $user->person->name,
            'phone' => null,
            'can_log_in' => false,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $role->id,
                'status' => null,
            ]],
        ])->assertSessionHasErrors('can_log_in');

        $this->assertTrue($user->person->fresh()->can_log_in);
    }

    public function test_nobody_can_turn_off_an_admin_login_before_owners_exist(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Admin Person', $role);
        $admin = User::factory()->create(['person_id' => $engagement->person_id]);
        $admin->forceFill(['is_admin' => true])->save();
        $engagement->person->update(['can_log_in' => true]);

        $this->actingAs($user)->get(route('settings.team.show', $engagement->person))->assertInertia(
            fn (Assert $page) => $page->where(
                'person.login_disable_reason',
                __('settings.team.login.admin_disabled'),
            ),
        );

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => $engagement->person->name,
            'phone' => null,
            'can_log_in' => false,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $role->id,
                'status' => null,
            ]],
        ])->assertSessionHasErrors('can_log_in');

        $this->assertTrue($engagement->person->fresh()->can_log_in);
    }

    public function test_login_switch_writes_require_manage_team_permission(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Permission Person', $role);
        Gate::define('manage-team', fn (): bool => false);

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Blocked Person',
            'email' => 'blocked@example.test',
            'can_log_in' => true,
            'status' => 'hired',
            'event_access' => [['event_id' => $event->id, 'role_id' => $role->id]],
        ])->assertForbidden();

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => $engagement->person->name,
            'phone' => null,
            'can_log_in' => true,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $role->id,
                'status' => null,
            ]],
        ])->assertForbidden();
    }

    public function test_person_page_refuses_locked_event_access_changes_and_rolls_back_profile_changes(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $otherRole = Role::query()->create(['name' => 'Manager']);
        $engagement = $this->engagement($event, 'Locked Member', $role);
        $event->lock();

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => 'Changed Name',
            'phone' => 'new phone',
            'can_log_in' => true,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $otherRole->id,
                'status' => null,
            ]],
        ])->assertSessionHasErrors('event_access.0.role_id');

        $this->assertSame('Locked Member', $engagement->person->fresh()->name);
        $this->assertSame($role->id, $engagement->fresh()->role_id);
    }

    public function test_removing_the_last_role_drops_the_person_from_global_team_but_keeps_the_record(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Staff']);
        $engagement = $this->engagement($event, 'Last Role', $role);

        $this->actingAs($user)->put(route('settings.team.update', $engagement->person), [
            'name' => 'Last Role',
            'phone' => null,
            'can_log_in' => true,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => null,
                'status' => null,
            ]],
        ])->assertRedirect(route('settings.team'));

        $this->assertDatabaseHas('team_engagements', [
            'id' => $engagement->id,
            'role_id' => null,
        ]);
        $this->assertTrue($engagement->person->fresh()->can_log_in);
        $this->actingAs($user)->get(route('settings.team.show', $engagement->person))->assertNotFound();
    }

    public function test_removed_event_users_routes_return_not_found(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();

        $this->actingAs($user)->get("/settings/events/{$event->id}/users")->assertNotFound();
        $this->actingAs($user)->get('/settings/users')->assertNotFound();
    }

    public function test_unauthenticated_team_settings_request_redirects_to_login(): void
    {
        $this->get(route('settings.team'))->assertRedirect(route('login'));
        $this->get(route('settings.team.data'))->assertRedirect(route('login'));
    }

    /** @return array{User, Event} */
    private function userWithCompletedSetup(): array
    {
        $user = User::factory()->create();
        $event = $this->event('Festival');
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
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

    private function engagement(Event $event, string $name, ?Role $role = null): TeamEngagement
    {
        $email = str($name)->lower()->replace(' ', '-').'@example.test';
        $person = Person::query()->create([
            'name' => $name,
            'email' => $email,
        ]);

        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'role_id' => $role?->id,
            'status' => 'applied',
            'employment_type' => 'volunteer',
        ]);
    }
}
