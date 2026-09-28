<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Support\OrganizationContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_unauthenticated_roles_request_redirects_to_login(): void
    {
        $this->get(route('settings.roles'))->assertRedirect(route('login'));
    }

    public function test_roles_table_is_organization_wide(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasColumn('roles', 'organization_id'));
        $this->assertFalse(Schema::hasColumn('roles', 'event_id'));
    }

    public function test_roles_list_starts_on_the_on_filter(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage manager']);
        Role::query()->create(['name' => 'Box office lead', 'active' => false]);

        $this->actingAs($user)->get(route('settings.roles'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/Roles')
                ->where('filters.status', 'on')
                ->where('filters.search', '')
                ->where('hasAnyRoles', true)
                ->has('roles.data', 1)
                ->where('roles.data.0.name', 'Stage manager')
                ->where('roles.data.0.active', true),
        );
    }

    public function test_off_and_all_filters_list_the_matching_roles(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage manager']);
        Role::query()->create(['name' => 'Box office lead', 'active' => false]);

        $this->actingAs($user)->get(route('settings.roles', ['status' => 'off']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.status', 'off')
                ->has('roles.data', 1)
                ->where('roles.data.0.name', 'Box office lead')
                ->where('roles.data.0.active', false),
        );

        $this->actingAs($user)->get(route('settings.roles', ['status' => 'all']))->assertInertia(
            fn (Assert $page) => $page
                ->has('roles.data', 2)
                ->where('roles.data.0.name', 'Stage manager')
                ->where('roles.data.1.name', 'Box office lead'),
        );
    }

    public function test_unknown_status_filter_is_rejected(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->get(route('settings.roles', ['status' => 'deleted']))
            ->assertSessionHasErrors('status');
    }

    public function test_search_filters_roles_by_name(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage manager']);
        Role::query()->create(['name' => 'Bartender']);
        Role::query()->create(['name' => 'Stage hand', 'active' => false]);

        $this->actingAs($user)->get(route('settings.roles', ['search' => '  STAGE  ', 'status' => 'all']))->assertInertia(
            fn (Assert $page) => $page
                ->where('filters.search', 'STAGE')
                ->has('roles.data', 2)
                ->where('roles.data.0.name', 'Stage manager')
                ->where('roles.data.1.name', 'Stage hand'),
        );
    }

    public function test_search_treats_like_wildcards_as_text(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage manager']);

        $this->actingAs($user)->get(route('settings.roles', ['search' => '%']))->assertInertia(
            fn (Assert $page) => $page->has('roles.data', 0),
        );
    }

    public function test_people_count_is_zero_until_roles_are_given_to_people(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage manager']);
        Role::query()->create(['name' => 'Box office lead', 'active' => false]);

        $this->actingAs($user)->get(route('settings.roles', ['status' => 'all']))->assertInertia(
            fn (Assert $page) => $page
                ->where('roles.data.0.people_count', 0)
                ->where('roles.data.1.people_count', 0),
        );
    }

    public function test_empty_list_reports_no_roles(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)->get(route('settings.roles'))->assertInertia(
            fn (Assert $page) => $page
                ->has('roles.data', 0)
                ->where('hasAnyRoles', false),
        );
    }

    public function test_staff_can_add_a_role_with_no_permissions(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => 'Stage manager'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', [
            'name' => 'Stage manager',
            'name_key' => 'stage manager',
            'active' => true,
        ]);
    }

    public function test_added_name_is_trimmed_and_inner_spaces_squashed(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => "  Stage \t  Manager  "])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Stage Manager', 'name_key' => 'stage manager']);
    }

    public function test_empty_name_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();

        foreach (['', '     ', "\u{00A0}\u{00A0}"] as $name) {
            $this->actingAs($user)
                ->post(route('settings.roles.store'), ['name' => $name])
                ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_required')]);
        }

        $this->assertDatabaseCount('roles', 0);
    }

    public function test_adding_a_name_that_differs_only_by_case_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage Manager']);

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => 'stage manager'])
            ->assertSessionHasErrors([
                'name' => __('settings.roles.validation.name_taken'),
                'name_match' => 'Stage Manager',
            ]);

        $this->assertDatabaseCount('roles', 1);
    }

    public function test_adding_a_name_that_differs_only_by_spaces_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage Manager']);

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => ' Stage   Manager '])
            ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_taken')]);

        $this->assertDatabaseCount('roles', 1);
    }

    public function test_accents_make_names_different(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Cafe']);

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => 'Café'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Café', 'name_key' => 'café']);
        $this->assertDatabaseCount('roles', 2);
    }

    public function test_an_off_role_still_holds_its_name(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Stage Manager', 'active' => false]);
        $other = Role::query()->create(['name' => 'Bartender']);

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => 'stage manager'])
            ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_taken')]);

        $this->actingAs($user)
            ->put(route('settings.roles.update', $other), ['name' => 'STAGE MANAGER'])
            ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_taken')]);

        $this->assertSame('Bartender', $other->fresh()->name);
    }

    public function test_staff_can_rename_a_role(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.update', $role), ['name' => 'Volunteer  coordinator '])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Volunteer coordinator', $role->fresh()->name);
        $this->assertSame('volunteer coordinator', $role->fresh()->name_key);
    }

    public function test_a_role_can_be_renamed_to_a_new_case_of_its_own_name(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'volunteer lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.update', $role), ['name' => 'Volunteer Lead'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Volunteer Lead', $role->fresh()->name);
    }

    public function test_renaming_to_a_clashing_name_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Staff']);
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.update', $role), ['name' => ' staff '])
            ->assertSessionHasErrors([
                'name' => __('settings.roles.validation.name_taken'),
                'name_match' => 'Staff',
            ]);

        $this->assertSame('Volunteer lead', $role->fresh()->name);
    }

    public function test_renaming_to_an_empty_name_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.update', $role), ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame('Volunteer lead', $role->fresh()->name);
    }

    public function test_staff_can_turn_a_role_off_and_back_on(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Box office lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.status.update', $role), ['active' => false])
            ->assertRedirect()
            ->assertSessionHas('success', __('settings.roles.toast.turned_off'));

        $this->assertFalse($role->fresh()->active);
        $this->assertDatabaseCount('roles', 1);

        $this->actingAs($user)
            ->put(route('settings.roles.status.update', $role), ['active' => true])
            ->assertSessionHas('success', __('settings.roles.toast.turned_on'));

        $this->assertTrue($role->fresh()->active);
    }

    public function test_status_change_requires_a_boolean(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Box office lead']);

        $this->actingAs($user)
            ->put(route('settings.roles.status.update', $role), [])
            ->assertSessionHasErrors('active');

        $this->actingAs($user)
            ->put(route('settings.roles.status.update', $role), ['active' => 'maybe'])
            ->assertSessionHasErrors('active');

        $this->assertTrue($role->fresh()->active);
    }

    public function test_roles_cannot_be_deleted(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Box office lead']);

        $this->actingAs($user)
            ->delete('/settings/roles/'.$role->id)
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount('roles', 1);
    }

    public function test_missing_role_returns_not_found(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->put('/settings/roles/999', ['name' => 'Anything'])
            ->assertNotFound();

        $this->actingAs($user)
            ->put('/settings/roles/999/status', ['active' => false])
            ->assertNotFound();
    }

    public function test_database_rejects_clashing_name_keys(): void
    {
        Role::query()->create(['name' => 'Stage Manager']);

        $this->expectException(UniqueConstraintViolationException::class);

        Role::query()->create(['name' => ' stage  manager']);
    }

    public function test_service_turns_a_unique_index_clash_into_a_validation_error(): void
    {
        Role::query()->create(['name' => 'Stage Manager']);

        try {
            app(RoleService::class)->create('STAGE MANAGER');
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                [__('settings.roles.validation.name_taken')],
                $exception->errors()['name'],
            );
            $this->assertSame(['Stage Manager'], $exception->errors()['name_match']);
        }
    }

    public function test_service_rethrows_unique_violations_that_are_not_name_clashes(): void
    {
        $first = Role::query()->create(['name' => 'Staff']);
        $second = Role::query()->create(['name' => 'Volunteer lead']);

        // Force a primary key clash while the new name itself is free.
        $second->id = $first->id;

        $this->expectException(UniqueConstraintViolationException::class);

        app(RoleService::class)->rename($second, 'Stage crew');
    }

    public function test_zero_width_characters_do_not_make_a_new_name(): void
    {
        $user = $this->userWithCompletedSetup();
        Role::query()->create(['name' => 'Staff']);

        foreach (["Staff\u{200B}", "\u{FEFF}Staff", "St\u{200C}a\u{200D}ff", "Staff\u{2060}", "Sta\u{00AD}ff"] as $name) {
            $this->actingAs($user)
                ->post(route('settings.roles.store'), ['name' => $name])
                ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_taken')]);
        }

        $this->assertDatabaseCount('roles', 1);
    }

    public function test_zero_width_characters_are_removed_from_the_saved_name(): void
    {
        $user = $this->userWithCompletedSetup();
        $role = Role::query()->create(['name' => 'Volunteer lead']);

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => "\u{200B}Stage\u{2060} \u{FEFF}manager\u{200D}"])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(route('settings.roles.update', $role), ['name' => "Volunteer\u{200B} coordinator"])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Stage manager', 'name_key' => 'stage manager']);
        $this->assertDatabaseHas('roles', ['name' => 'Volunteer coordinator', 'name_key' => 'volunteer coordinator']);
    }

    public function test_a_name_made_only_of_zero_width_characters_is_refused(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->actingAs($user)
            ->post(route('settings.roles.store'), ['name' => "\u{200B}\u{FEFF}\u{2060}"])
            ->assertSessionHasErrors(['name' => __('settings.roles.validation.name_required')]);

        $this->assertDatabaseCount('roles', 0);
    }

    public function test_event_roles_tab_routes_are_gone(): void
    {
        $user = $this->userWithCompletedSetup();

        $this->assertFalse(app('router')->has('settings.events.roles'));

        $this->actingAs($user)
            ->get('/settings/events/'.$this->event->id.'/roles')
            ->assertNotFound();

        $this->actingAs($user)->get('/settings/roles')->assertInertia(
            fn (Assert $page) => $page
                ->component('Settings/Roles')
                ->missing('event')
                ->missing('tab'),
        );
    }

    public function test_primary_missing_message_no_longer_mentions_roles(): void
    {
        $this->assertStringNotContainsString('Roles', __('settings.events.primary_missing'));
    }

    private function userWithCompletedSetup(): User
    {
        $user = User::factory()->create();
        $this->event = Event::query()->create([
            'name' => 'Festival',
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($this->event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($this->event);

        return $user;
    }
}
