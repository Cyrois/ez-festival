<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Support\OrganizationContext;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Festival A');
        $this->user = User::factory()->create();
        $this->user->person()->update(['can_log_in' => true]);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->role = Role::create(['name' => 'Limited access', 'permissions' => ['artists.view']]);
        $this->membership($this->event, $this->user->person, $this->role);
        $this->actingAs($this->user);
    }

    public function test_every_route_has_an_authorization_check_or_a_documented_exception(): void
    {
        $exceptions = [
            '/' => 'Redirects to sign-in or the authorized home.',
            'login' => 'Public sign-in, with credentials checked by the controller.',
            'forgot-password' => 'Public password recovery.',
            'logout' => 'A signed-in person can always sign out.',
            'change-temporary-password' => 'The person changes their own password.',
            'team-invitations/{token}' => 'Public invitation, authorized by its secret token.',
            'form/{slug}' => 'Public application form, controlled by its published slug.',
            'form/{slug}/confirmation' => 'Public form confirmation.',
            'dashboard' => 'Event home is available to every active role; event.access and login.access enforce access.',
            'events' => 'Lists only accessible events.',
            'events/{event}/current' => 'The Form Request and event.access allow selection only of an accessible event.',
            'events/{event}' => 'event.access checks access to the bound event.',
            'settings/account' => 'Only the signed-in person’s own account.',
            'settings/account/password' => 'Only the signed-in person’s own password.',
            'up' => 'Public health probe.',
            '_boost/browser-logs' => 'Development-only Boost browser diagnostics.',
            'storage/{path}' => 'Framework storage route checks its signed URL.',
        ];
        foreach (Route::getRoutes() as $route) {
            $checked = collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'can:'));
            $this->assertTrue($checked || isset($exceptions[$route->uri()]), 'Missing permission check: '.$route->methods()[0].' '.$route->uri());
        }
    }

    public function test_view_only_artist_role_gets_home_and_an_event_only_permission_map(): void
    {
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($permissions) => $permissions['artists.view'] === true && $permissions['artists.edit'] === false && $permissions['team.view'] === false)
            ->has('permissions', count(Permissions::keys()))
            ->missing('permissions.role')
            ->missing('permissions.events'));
        $this->get(route('artists.index'))->assertOk();
        foreach (['artists.create', 'vendors.advancing', 'check-in.index', 'team.advancement', 'patrons.index', 'credentials.passes', 'credentials.entitlements', 'credentials.products', 'settings.events.index', 'settings.roles', 'settings.roles.create', 'setup.event'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('settings.account'))->assertOk();
    }

    public function test_area_view_and_edit_routes_require_their_permissions(): void
    {
        $this->role->update(['permissions' => ['team.notes.read']]);
        foreach (['artists.index', 'artists.create', 'vendors.advancing', 'vendors.create', 'check-in.index', 'team.advancement', 'team.members.create', 'patrons.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        foreach (['artists.store', 'vendors.store', 'team.members.store'] as $route) {
            $this->post(route($route, $this->event), [])->assertForbidden();
        }
        $artist = $this->artist($this->event);
        $person = Person::create(['name' => 'Contact', 'email' => 'contact@example.test']);
        $artist->people()->attach($person);
        $pass = $this->event->passTypes()->create(['name' => 'Guest']);
        $assignment = $artist->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $person->id]);
        $item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id, 'status' => 'expected']);
        $this->post(route('check-in.issues.store', $expected), [])->assertForbidden();
    }

    public function test_current_event_selection_requires_an_active_role_at_the_target_event(): void
    {
        $other = $this->event('Accessible event');
        $foreign = $this->event('Inaccessible event');
        $this->membership($other, $this->user->person, $this->role);
        $this->put(route('events.current.update', $foreign))->assertNotFound();
        $this->assertSame($this->event->id, $this->user->fresh()->current_event_id);
        $this->put(route('events.current.update', $other))->assertRedirect(route('events.index'));
        $this->assertSame($other->id, $this->user->fresh()->current_event_id);
    }

    public function test_record_event_is_used_even_if_header_event_has_the_permission(): void
    {
        $other = $this->event('Festival B');
        $otherRole = Role::create(['name' => 'Other event role', 'permissions' => ['vendors.view']]);
        $this->membership($other, $this->user->person, $otherRole);
        $record = $this->artist($other);
        $this->get(route('artists.view', $record))->assertForbidden();
        $this->put(route('artists.update', $record), [])->assertForbidden();
        $otherRole->update(['permissions' => ['artists.edit']]);
        $this->user->setCurrentEvent($other);
        $this->get(route('artists.view', $record))->assertInertia(fn (Assert $page) => $page->where('canWrite', true));
        $this->user->setCurrentEvent($this->event);
        $this->get(route('artists.create'))->assertForbidden();
    }

    public function test_permission_removal_unknown_saved_keys_and_off_roles_apply_on_the_next_request(): void
    {
        $this->get(route('artists.index'))->assertOk();
        $this->role->update(['permissions' => ['team.view', 'removed.permission']]);
        $this->get(route('artists.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->missing('permissions.removed.permission'));
        $fallback = $this->event('Fallback');
        $this->membership($fallback, $this->user->person, Role::create(['name' => 'Fallback role', 'permissions' => ['team.view']]));
        $this->role->update(['active' => false]);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('activeEvent.id', $fallback->id));
    }

    public function test_admin_has_all_permissions_without_a_role_but_cannot_write_a_locked_event(): void
    {
        $this->grantAdminAccess($this->user);
        TeamEngagement::where('person_id', $this->user->person_id)->delete();
        $this->get(route('dashboard'))->assertInertia(function (Assert $page): void {
            foreach (Permissions::keys() as $key) {
                $page->where('permissions', fn ($permissions) => $permissions[$key] === true);
            }
        });
        $this->get(route('settings.roles.create'))->assertOk();
        $this->event->lock();
        $this->post(route('artists.store', $this->event), ['name' => 'Locked artist'])->assertForbidden();
        $this->role->update(['permissions' => ['artists.edit']]);
        $this->user->forceFill(['is_admin' => false])->save();
        $this->membership($this->event, $this->user->person, $this->role);
        $this->post(route('artists.store', $this->event), ['name' => 'Still locked'])->assertForbidden();
    }

    public function test_role_pages_validation_includes_and_round_trip(): void
    {
        $this->grantAdminAccess($this->user);
        $this->get(route('settings.roles.create'))->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Roles/Create')->has('permissionGroups', 5)->has('permissionGroups.team', 6));
        foreach ([[], ['invented.permission']] as $permissions) {
            $this->post(route('settings.roles.store'), ['name' => 'Invalid', 'permissions' => $permissions])->assertSessionHasErrors();
        }
        $this->assertDatabaseMissing('roles', ['name' => 'Invalid']);
        $this->post(route('settings.roles.store'), ['name' => 'Editor', 'permissions' => ['team.edit', 'patrons.personal_info']])->assertRedirect(route('settings.roles'));
        $role = Role::where('name', 'Editor')->sole();
        foreach (['team.view', 'team.notes.read', 'team.notes.add', 'team.personal_info', 'patrons.view'] as $key) {
            $this->assertContains($key, $role->permissions);
        }
        $this->get(route('settings.roles.edit', $role))->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Roles/Edit')->where('role.permissions', $role->permissions));
        $this->put(route('settings.roles.update', $role), ['name' => 'Viewer', 'permissions' => ['artists.view']])->assertRedirect(route('settings.roles'));
        $this->assertSame(['artists.view'], $role->fresh()->permissions);
    }

    public function test_artist_vendor_and_checkin_data_omit_personal_info_and_give_pass_counts(): void
    {
        $this->role->update(['permissions' => ['artists.view', 'vendors.view', 'checkin.view']]);
        $artist = $this->artist($this->event);
        $vendor = VendorEngagement::create(['event_id' => $this->event->id, 'vendor_id' => Vendor::create(['name' => 'Vendor'])->id, 'status' => 'confirmed']);
        $person = Person::create(['name' => 'Private contact', 'email' => 'secret@example.test', 'phone' => '555-secret']);
        $artist->people()->attach($person, ['is_primary' => true]);
        $vendor->people()->attach($person, ['is_primary' => true]);
        $pass = $this->event->passTypes()->create(['name' => 'Full guest pass', 'max_assignments' => 1]);
        $artist->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $person->id]);
        foreach (['artists.view' => $artist, 'vendors.view' => $vendor] as $route => $engagement) {
            $this->get(route($route, $engagement))->assertInertia(fn (Assert $page) => $page
                ->where('canWrite', false)->where('engagement.people.0.personal_info_hidden', true)
                ->missing('engagement.people.0.email')->missing('engagement.people.0.phone')
                ->where('passes.0.full', true)->missing('passes.0.max_assignments')->missing('passes.0.assignments_count')->missing('passes.0.assigned_count'));
        }
        $this->get(route('check-in.index'))->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.personal_info_hidden', true)->missing('people.data.0.subtitle'));
        $this->get(route('check-in.show', $artist))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', false)->where('engagement.people.0.personal_info_hidden', true)->missing('engagement.people.0.email'));
        $this->role->update(['permissions' => ['artists.edit', 'vendors.edit', 'checkin.edit']]);
        $this->get(route('artists.view', $artist))->assertInertia(fn (Assert $page) => $page->where('engagement.people.0.email', 'secret@example.test')->where('canWrite', true));
        $this->get(route('vendors.view', $vendor))->assertInertia(fn (Assert $page) => $page->where('engagement.people.0.phone', '555-secret'));
        $this->get(route('check-in.show', $artist))->assertInertia(fn (Assert $page) => $page->where('canWrite', true)->where('engagement.people.0.email', 'secret@example.test'));
        $this->grantAdminAccess($this->user);
        $this->get(route('artists.view', $artist))->assertInertia(fn (Assert $page) => $page->missing('passes.0.max_assignments')->missing('passes.0.assignments_count'));
        $this->get(route('credentials.passes'))->assertInertia(fn (Assert $page) => $page->where('passes.0.max_assignments', 1)->where('passes.0.assigned_count', 1));
    }

    public function test_team_view_redacts_personal_info_and_read_only_notes_cannot_be_added(): void
    {
        $this->role->update(['permissions' => ['team.view', 'team.notes.read']]);
        $target = $this->membership($this->event, Person::create(['name' => 'Target', 'email' => 'team-secret@example.test', 'phone' => '555-private']));
        $target->notes()->create(['user_id' => $this->user->id, 'body' => 'Visible note']);
        $this->get(route('team.members.show', $target))->assertInertia(fn (Assert $page) => $page
            ->where('engagement.personal_info_hidden', true)->missing('engagement.email')->missing('engagement.phone')
            ->where('canAddNotes', false)->where('notes.0.editable_until', null));
        $this->put(route('team.members.update', $target), ['notes' => [['body' => 'Forbidden']]])->assertForbidden();
        $this->role->update(['permissions' => ['team.view']]);
        $this->get(route('team.members.show', $target))->assertInertia(fn (Assert $page) => $page->missing('notes'));
        $this->get(route('team.advancement'))->assertInertia(fn (Assert $page) => $page->missing('engagements.data.0.email')->missing('engagements.data.0.phone'));
    }

    public function test_notes_only_member_can_add_and_edit_own_notes_without_editing_details(): void
    {
        $this->role->update(['permissions' => ['team.view', 'team.notes.add']]);
        $target = $this->membership($this->event, Person::create(['name' => 'Read only target', 'email' => 'readonly@example.test']));
        $this->get(route('team.members.show', $target))->assertInertia(fn (Assert $page) => $page->where('canWrite', false)->where('canAddNotes', true)->where('canReadNotes', true));
        $this->put(route('team.members.update', $target), ['notes' => [['body' => 'Own new note']]])->assertSessionHasNoErrors();
        $note = $target->notes()->sole();
        $this->put(route('team.members.update', $target), ['note_edits' => [['id' => $note->id, 'body' => 'Own edited note']]])->assertSessionHasNoErrors();
        $this->assertSame('Own edited note', $note->fresh()->body);
        $this->put(route('team.members.update', $target), ['name' => 'Forbidden rename'])->assertSessionHasErrors('name');
        $this->put(route('team.members.update', $target), ['pass_assignments' => []])->assertSessionHasErrors('pass_assignments');
        $this->assertSame('Read only target', $target->person->fresh()->name);
        $this->travel(6)->minutes();
        $this->put(route('team.members.update', $target), ['note_edits' => [['id' => $note->id, 'body' => 'Too late']]])->assertSessionHas('warning');
        $this->assertSame('Own edited note', $note->fresh()->body);
    }

    public function test_role_change_permission_has_self_and_escalation_guards(): void
    {
        $this->role->update(['permissions' => ['team.change_role', 'artists.view']]);
        $small = Role::create(['name' => 'Small', 'permissions' => ['artists.view']]);
        $large = Role::create(['name' => 'Too much', 'permissions' => ['artists.edit']]);
        $target = $this->membership($this->event, Person::create(['name' => 'Target', 'email' => 'target@example.test']), $small);
        $this->get(route('team.members.show', $target))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', false)->where('canChangeRole', true)
            ->where('roles', fn ($roles) => ! collect($roles)->contains('id', $large->id)));
        $this->put(route('team.members.update', $target), ['role_id' => $large->id])->assertSessionHasErrors('role_id');
        $this->put(route('team.members.update', $target), ['role_id' => 'invalid'])->assertSessionHasErrors('role_id');
        $this->put(route('team.members.update', $target), ['role_id' => $this->role->id])->assertSessionHasNoErrors();
        $self = TeamEngagement::where('person_id', $this->user->person_id)->sole();
        $this->get(route('team.members.show', $self))->assertInertia(fn (Assert $page) => $page->where('canChangeRole', false));
        $this->put(route('team.members.update', $self), ['role_id' => $small->id])->assertSessionHasErrors('role_id');
        $this->role->update(['permissions' => ['team.edit']]);
        $this->put(route('team.members.update', $target), [
            'name' => $target->person->name, 'email' => $target->person->email,
            'status' => 'hired', 'employment_type' => 'volunteer', 'role_id' => $small->id,
        ])->assertSessionHasErrors('role_id');
        $this->grantAdminAccess($this->user);
        $this->put(route('team.members.update', $self), [
            'name' => $this->user->name, 'email' => $this->user->email,
            'status' => 'hired', 'employment_type' => 'volunteer', 'role_id' => $large->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_non_admin_cannot_lock_events_or_change_global_settings(): void
    {
        $this->role->update(['permissions' => Permissions::keys()]);
        $this->post(route('events.lock', $this->event))->assertForbidden();
        $this->post(route('events.unlock', $this->event))->assertForbidden();
        $this->post(route('settings.roles.store'), ['name' => 'Forbidden', 'permissions' => Permissions::keys()])->assertForbidden();
        $this->post(route('credentials.passes.store', $this->event), [])->assertForbidden();
        $this->post(route('credentials.entitlements.store', $this->event), [])->assertForbidden();
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
    }

    private function membership(Event $event, Person $person, ?Role $role = null): TeamEngagement
    {
        return TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'role_id' => $role?->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function artist(Event $event): ArtistEngagement
    {
        return ArtistEngagement::create(['event_id' => $event->id, 'artist_id' => Artist::create(['name' => 'Artist '.$event->id])->id, 'status' => 'confirmed']);
    }
}
