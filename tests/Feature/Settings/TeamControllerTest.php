<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        [$user, $event] = $this->userWithCompletedSetup();
        $second = $this->event('Winter Lights');
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $response = $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Ava Lee',
            'email' => ' AVA.LEE@EXAMPLE.TEST ',
            'phone' => null,
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
            'status' => 'applied',
            'event_access' => [],
        ])->assertSessionHasErrors(['name', 'email', 'event_access']);

        $this->actingAs($user)->post(route('settings.team.store'), [
            'name' => 'Locked Person',
            'email' => 'locked@example.test',
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
