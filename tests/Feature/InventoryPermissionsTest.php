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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Festival');
        $this->user = User::factory()->create();
        $this->role = Role::create(['name' => 'Manager', 'permissions' => ['inventory.manage']]);
        TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $this->user->person_id,
            'role_id' => $this->role->id, 'status' => 'hired']);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_inventory_role_permissions_are_independent_and_manage_includes_both_passes_only(): void
    {
        $this->assertSame(['inventory.pass_shift'], Permissions::expand(['inventory.pass_shift']));
        $this->assertSame(['inventory.pass_group'], Permissions::expand(['inventory.pass_group']));
        $this->assertSame(['inventory.manage', 'inventory.pass_shift', 'inventory.pass_group'], Permissions::expand(['inventory.manage']));
        $this->assertSame(['checkin.edit', 'checkin.view'], Permissions::expand(['checkin.edit']));
        $this->assertSame('Check people in', __('permissions.checkin.edit.label'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($permissions) => $permissions['inventory.manage']
                && $permissions['inventory.pass_shift'] && $permissions['inventory.pass_group']
                && ! $permissions['checkin.edit'] && ! $permissions['credentials.stock.adjust']
                && ! isset($permissions['inventory.handle'])));

        $this->grantAdminAccess($this->user);
        $this->get(route('settings.roles.create'))->assertInertia(fn (Assert $page) => $page
            ->has('permissionGroups.inventory', 3)->where('permissionGroups.inventory.0.label', 'Pass stock within my shift')
            ->where('permissionGroups.inventory.1.label', 'Pass stock within my group')
            ->where('permissionGroups.inventory.2.label', 'Manage inventory')
            ->where('permissionGroups.inventory.2.includes', ['inventory.pass_shift', 'inventory.pass_group']));
        foreach (['inventory.pass_shift', 'inventory.pass_group', 'inventory.manage'] as $key) {
            $this->post(route('settings.roles.store'), ['name' => $key, 'permissions' => [$key]])->assertSessionHasNoErrors();
            $role = Role::where('name', $key)->sole();
            $this->assertSame(Permissions::expand([$key]), $role->permissions);
            $this->get(route('settings.roles.edit', $role))->assertInertia(fn (Assert $page) => $page
                ->has('permissionGroups.inventory', 3)->where('role.permissions', $role->permissions));
        }
        $this->post(route('settings.roles.store'), ['name' => 'Retired permission', 'permissions' => ['inventory.handle']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_manager_entitlements_are_read_only_including_deep_links_and_issued_log(): void
    {
        $item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->get(route('credentials.entitlements'))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', true)->where('canEditItems', false)->where('canAdjustStock', false));
        $this->get(route('credentials.entitlements.show', $item, false).'?adjust=1&loc='.$location->id)
            ->assertInertia(fn (Assert $page) => $page->where('is_read_only', false)
                ->where('canEditItems', false)->where('canAdjustStock', false)->where('open_adjust', false));
        $this->getJson(route('credentials.entitlements.issued', $item).'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->get(route('credentials.entitlements.edit', $item))->assertForbidden();
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
        $this->put(route('credentials.entitlements.update', [$this->event, $item]), ['name' => 'Forbidden'])->assertForbidden();
        $this->delete(route('credentials.entitlements.destroy', [$this->event, $item]))->assertForbidden();
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $item]), [
            'location_id' => $location->id, 'direction' => 'add', 'quantity' => 1,
        ])->assertForbidden();
        foreach (['credentials.passes', 'credentials.products', 'settings.events.index', 'settings.roles', 'settings.team'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('settings.account'))->assertOk();
        $this->assertSame('Wristband', $item->fresh()->name);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_credentials_permissions_add_only_their_own_capabilities_to_manage_inventory(): void
    {
        $item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        foreach (['credentials.entitlements.edit', 'credentials.stock.adjust', 'credentials.passes.edit'] as $key) {
            $this->role->update(['permissions' => ['inventory.manage', $key]]);
            $this->get(route('credentials.entitlements.show', $item))->assertInertia(fn (Assert $page) => $page
                ->where('canEditItems', $key === 'credentials.entitlements.edit')
                ->where('canAdjustStock', $key === 'credentials.stock.adjust'));
            $response = $this->get(route('credentials.passes'));
            $key === 'credentials.passes.edit' ? $response->assertOk() : $response->assertForbidden();
            $this->get(route('credentials.products'))->assertForbidden();
        }
    }

    public function test_manager_can_add_and_rename_locations_without_changing_types_or_deleting(): void
    {
        $this->get(route('settings.locations'))->assertInertia(fn (Assert $page) => $page
            ->where('event.id', $this->event->id)->where('canAddLocations', true)
            ->where('canRenameLocations', true)->where('canDeleteLocations', false)->where('canEditLocationTypes', false));
        $this->post(route('settings.events.locations.store', $this->event), ['name' => 'Gate'])->assertSessionHasNoErrors();
        $location = $this->event->locations()->where('name', 'Gate')->sole();
        $location->update(['type' => 'stage']);
        $this->put(route('settings.events.locations.update', [$this->event, $location]), ['name' => 'New Gate'])
            ->assertSessionHasNoErrors();
        $this->assertSame('stage', $location->fresh()->type);
        $this->assertSame('New Gate', $location->fresh()->name);
        foreach (['bar', '', null] as $type) {
            $this->put(route('settings.events.locations.update', [$this->event, $location]), ['name' => 'Unauthorized type', 'type' => $type])
                ->assertSessionHasErrors('type');
            $this->post(route('settings.events.locations.store', $this->event), ['name' => 'Unauthorized create', 'type' => $type])
                ->assertSessionHasErrors('type');
        }
        $this->post(route('settings.events.locations.store', $this->event), ['name' => ''])->assertSessionHasErrors('name');
        $this->put(route('settings.events.locations.update', [$this->event, $location]), ['name' => str_repeat('x', 256)])
            ->assertSessionHasErrors('name');
        $this->delete(route('settings.events.locations.destroy', [$this->event, $location]))->assertForbidden();
        $this->assertSame('New Gate', $location->fresh()->name);
        $this->assertDatabaseMissing('locations', ['name' => 'Unauthorized create']);
    }

    public function test_manager_location_access_is_limited_to_current_event_and_foreign_ids_are_refused(): void
    {
        $other = $this->event('Other event');
        TeamEngagement::create(['event_id' => $other->id, 'person_id' => $this->user->person_id,
            'role_id' => $this->role->id, 'status' => 'hired']);
        $location = $other->locations()->create(['name' => 'Other Gate']);
        $item = $other->entitlementItems()->create(['name' => 'Other stock']);
        $this->get(route('settings.events.locations', $other))->assertNotFound();
        $this->post(route('settings.events.locations.store', $other), ['name' => 'Forbidden'])->assertNotFound();
        $this->put(route('settings.events.locations.update', [$other, $location]), ['name' => 'Forbidden'])->assertNotFound();
        $this->put(route('settings.events.locations.update', [$this->event, $location]), ['name' => 'Foreign ID'])->assertNotFound();
        $this->get(route('credentials.entitlements.show', $item))->assertNotFound();
        $this->user->setCurrentEvent($other);
        $this->get(route('settings.locations'))->assertInertia(fn (Assert $page) => $page->where('event.id', $other->id));
        $this->get(route('settings.events.locations', $other))->assertOk();
        $this->assertSame('Other Gate', $location->fresh()->name);
    }

    public function test_lock_blocks_manager_location_writes_but_keeps_reads_available(): void
    {
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $this->event->lock();
        foreach (['settings.locations' => [], 'settings.events.locations' => [$this->event]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertInertia(fn (Assert $page) => $page
                ->where('event.is_read_only', true)->where('canAddLocations', false)
                ->where('canRenameLocations', false)->where('canDeleteLocations', false));
        }
        $this->get(route('credentials.entitlements.show', $item))->assertInertia(fn (Assert $page) => $page->where('is_read_only', true));
        $this->post(route('settings.events.locations.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
        $this->put(route('settings.events.locations.update', [$this->event, $location]), ['name' => 'Forbidden'])->assertForbidden();
        $this->assertSame('Gate', $location->fresh()->name);
    }

    public function test_admin_without_team_can_manage_other_event_locations_and_history_still_blocks_delete(): void
    {
        $this->grantAdminAccess($this->user);
        TeamEngagement::where('person_id', $this->user->person_id)->delete();
        $other = $this->event('Other event');
        $location = $other->locations()->create(['name' => 'Other Gate']);
        $this->get(route('settings.events.locations', $other))->assertInertia(fn (Assert $page) => $page
            ->where('canDeleteLocations', true)->where('canEditLocationTypes', true));
        $this->put(route('settings.events.locations.update', [$other, $location]), ['name' => 'Changed', 'type' => 'bar'])
            ->assertSessionHasNoErrors();
        $this->assertSame('bar', $location->fresh()->type);
        $item = $other->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedStock($item, $location, 2, $this->user);
        $this->seedStock($item, $location, -2, $this->user);
        $this->delete(route('settings.events.locations.destroy', [$other, $location]))->assertSessionHasErrors('location');
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $unused = $other->locations()->create(['name' => 'Unused']);
        $this->delete(route('settings.events.locations.destroy', [$other, $unused]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('locations', ['id' => $unused->id]);
        $other->lock();
        $this->post(route('settings.events.locations.store', $other), ['name' => 'Locked'])->assertForbidden();
    }

    public function test_permission_revocation_and_event_role_scope_apply_on_the_next_request(): void
    {
        $this->get(route('settings.locations'))->assertOk();
        $this->get(route('credentials.entitlements'))->assertOk();
        foreach (['inventory.pass_shift', 'inventory.pass_group', 'checkin.view'] as $key) {
            $this->role->update(['permissions' => [$key]]);
            $this->get(route('settings.locations'))->assertForbidden();
            $this->get(route('credentials.entitlements'))->assertForbidden();
        }
        $this->role->update(['permissions' => ['inventory.manage']]);
        $other = $this->event('Other');
        $otherRole = Role::create(['name' => 'Other role', 'permissions' => ['checkin.view']]);
        TeamEngagement::create(['event_id' => $other->id, 'person_id' => $this->user->person_id,
            'role_id' => $otherRole->id, 'status' => 'hired']);
        $this->get(route('settings.events.locations', $other))->assertForbidden();
        $this->role->update(['active' => false]);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('activeEvent.id', $other->id)->where('permissions', fn ($permissions) => ! $permissions['inventory.manage']));
    }

    public function test_inventory_permissions_do_not_allow_consuming_artist_vendor_or_team_entitlements(): void
    {
        $person = Person::create(['name' => 'Receiver', 'email' => 'receiver@example.test']);
        $artist = ArtistEngagement::create(['event_id' => $this->event->id,
            'artist_id' => Artist::create(['name' => 'Artist'])->id, 'status' => 'confirmed']);
        $vendor = VendorEngagement::create(['event_id' => $this->event->id,
            'vendor_id' => Vendor::create(['name' => 'Vendor'])->id, 'status' => 'confirmed']);
        $artist->people()->attach($person);
        $vendor->people()->attach($person);
        $team = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $person->id, 'status' => 'hired']);
        $pass = $this->event->passTypes()->create(['name' => 'Staff']);
        $item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $targets = [[$artist, 'check-in.show'], [$vendor, 'check-in.vendors.show'], [$team, 'check-in.teams.show']];
        foreach (['inventory.manage', 'inventory.pass_shift', 'inventory.pass_group'] as $permission) {
            $this->role->update(['permissions' => [$permission, 'checkin.view']]);
            foreach ($targets as [$engagement, $route]) {
                $this->get(route($route, $engagement))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
            }
        }
        foreach ($targets as [$engagement, $route]) {
            $field = $engagement instanceof ArtistEngagement ? 'artist_engagement_id' : ($engagement instanceof VendorEngagement ? 'vendor_engagement_id' : 'team_engagement_id');
            $assignment = $pass->assignments()->create([$field => $engagement->id, 'person_id' => $person->id]);
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id, 'status' => 'expected']);
            $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertForbidden();
        }
        $this->role->update(['permissions' => ['checkin.edit']]);
        foreach ($targets as [$engagement, $route]) {
            $this->get(route($route, $engagement))->assertInertia(fn (Assert $page) => $page->where('canWrite', true));
        }
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
    }
}
