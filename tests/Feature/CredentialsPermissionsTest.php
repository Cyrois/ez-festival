<?php

namespace Tests\Feature;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\PassType;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CredentialsPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $user;

    private Role $role;

    private EntitlementItem $item;

    private PassType $pass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $this->user = User::factory()->create();
        $this->user->person()->update(['can_log_in' => true]);
        $this->user->setCurrentEvent($this->event);
        $this->role = Role::create(['name' => 'Credentials staff', 'permissions' => []]);
        $this->createTeamEngagement(['event_id' => $this->event->id, 'person_id' => $this->user->person_id, 'role_id' => $this->role->id, 'status' => 'hired']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $this->pass = $this->event->passTypes()->create(['name' => 'Artist']);
        $this->actingAs($this->user);
    }

    public function test_role_pages_expose_and_persist_three_independent_credentials_permissions(): void
    {
        $this->grantAdminAccess($this->user);
        $this->get(route('settings.roles.create'))->assertInertia(fn (Assert $page) => $page
            ->has('permissionGroups.credentials', 3)
            ->where('permissionGroups.credentials.0.label', 'Create passes')
            ->where('permissionGroups.credentials.1.label', 'Create entitlements')
            ->where('permissionGroups.credentials.2.label', 'Add and remove stock')
            ->where('permissionGroups.credentials.2.tooltip', "Add stock to a location, or remove it with a reason (lost, damaged, counted wrong), on an entitlement's page."));
        foreach (['credentials.passes.edit', 'credentials.entitlements.edit', 'credentials.stock.adjust'] as $key) {
            $this->post(route('settings.roles.store'), ['name' => $key, 'permissions' => [$key]])->assertSessionHasNoErrors();
            $role = Role::where('name', $key)->sole();
            $this->assertSame([$key], $role->permissions);
            $this->get(route('settings.roles.edit', $role))->assertInertia(fn (Assert $page) => $page
                ->has('permissionGroups.credentials', 3)->where('role.permissions', [$key]));
        }
    }

    public function test_all_permission_combinations_have_independent_page_and_write_access(): void
    {
        for ($mask = 0; $mask < 8; $mask++) {
            $passes = (bool) ($mask & 1);
            $items = (bool) ($mask & 2);
            $stock = (bool) ($mask & 4);
            $permissions = array_keys(array_filter([
                'credentials.passes.edit' => $passes,
                'credentials.entitlements.edit' => $items,
                'credentials.stock.adjust' => $stock,
            ]));
            $this->role->update(['permissions' => $permissions]);
            $this->get(route('credentials.passes'))->assertStatus($passes ? 200 : 403);
            $this->get(route('credentials.passes.create'))->assertStatus($passes ? 200 : 403);
            $this->get(route('credentials.passes.edit', $this->pass))->assertStatus($passes ? 200 : 403);
            $this->get(route('credentials.entitlements'))->assertStatus($items || $stock ? 200 : 403);
            $this->get(route('credentials.entitlements.show', $this->item))->assertStatus($items || $stock ? 200 : 403);
            $this->get('/credentials/entitlements/'.$this->item->id.'/edit')->assertNotFound();
            $this->getJson(route('credentials.entitlements.history', ['entitlementItem' => $this->item, 'draw' => 1, 'start' => 0, 'length' => 10]))->assertStatus($items || $stock ? 200 : 403);
            $this->get(route('credentials.products'))->assertForbidden();
            $this->get(route('settings.locations'))->assertForbidden();
            $this->put(route('credentials.passes.update', [$this->event, $this->pass]), ['name' => 'Artist'])->assertStatus($passes ? 302 : 403);
            $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), ['name' => 'Wristband'])->assertStatus($items ? 302 : 403);
            $response = $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), [
                'location_id' => null, 'direction' => 'add', 'quantity' => 1,
            ]);
            if ($stock) {
                $response->assertSessionHasErrors('location_id');
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_pass_permission_can_create_edit_and_delete_with_item_names_and_catalog_totals(): void
    {
        $this->role->update(['permissions' => ['credentials.passes.edit']]);
        $this->get(route('credentials.passes.create'))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', true)->where('entitlementItems.0.name', 'Wristband')->missing('entitlementItems.0.balance'));
        $this->post(route('credentials.passes.store', $this->event), [
            'name' => 'Guest', 'entitlement_item_ids' => [$this->item->id],
        ])->assertRedirect(route('credentials.passes'));
        $pass = $this->event->passTypes()->where('name', 'Guest')->sole();
        $this->get(route('credentials.passes'))->assertInertia(fn (Assert $page) => $page
            ->where('passes', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $pass->id && $row['assigned_count'] === 0)));
        $this->put(route('credentials.passes.update', [$this->event, $pass]), ['name' => 'Guest renamed'])->assertSessionHasNoErrors();
        $this->delete(route('credentials.passes.destroy', [$this->event, $pass]))->assertRedirect(route('credentials.passes'));
        $this->assertDatabaseMissing('pass_types', ['id' => $pass->id]);
        $this->post(route('credentials.passes.store', $this->event), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_item_permission_creates_zero_stock_and_cannot_supply_a_positive_opening_amount(): void
    {
        $this->role->update(['permissions' => ['credentials.entitlements.edit']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Zero stock'])->assertSessionHasNoErrors();
        $item = $this->event->entitlementItems()->where('name', 'Zero stock')->sole();
        $this->assertSame(0, $item->movements()->count());
        $this->post(route('credentials.entitlements.store', $this->event), [
            'name' => 'Unauthorized opening', 'opening_balance' => 1, 'location_id' => $location->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Unauthorized opening']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->post(route('credentials.entitlements.store', $this->event), [
            'name' => 'Negative opening', 'opening_balance' => -1,
        ])->assertSessionHasErrors('opening_balance');
        $this->put(route('credentials.entitlements.update', [$this->event, $item]), [
            'name' => 'Renamed zero stock', 'opening_balance' => 10, 'location_id' => $location->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $item->movements()->count());
        $this->get(route('credentials.entitlements.show', $item, false).'?adjust=1&loc='.$location->id)
            ->assertInertia(fn (Assert $page) => $page->where('canEditItems', true)->where('canAdjustStock', false)
                ->where('is_read_only', false)->where('open_adjust', false));
        $this->delete(route('credentials.entitlements.destroy', [$this->event, $item]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('entitlement_items', ['id' => $item->id]);
    }

    public function test_combined_permissions_allow_opening_stock_and_require_an_event_location(): void
    {
        $this->role->update(['permissions' => ['credentials.entitlements.edit', 'credentials.stock.adjust']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->post(route('credentials.entitlements.store', $this->event), [
            'name' => 'Opening stock', 'opening_balance' => 5, 'location_id' => $location->id,
        ])->assertSessionHasNoErrors();
        $item = $this->event->entitlementItems()->where('name', 'Opening stock')->sole();
        $this->assertDatabaseHas('stock_movements', ['entitlement_item_id' => $item->id, 'type' => 'add', 'quantity' => 5, 'actor_user_id' => $this->user->id]);
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Missing location', 'opening_balance' => 1])
            ->assertSessionHasErrors('location_id');
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Missing location']);
        $this->post(route('credentials.entitlements.store', $this->event), [
            'name' => 'Excessive opening', 'opening_balance' => 2147483648, 'location_id' => $location->id,
        ])->assertSessionHasErrors('opening_balance');
        $this->put(route('credentials.entitlements.update', [$this->event, $item]), [
            'name' => 'Renamed', 'opening_balance' => 99,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $item->movements()->count());
    }

    public function test_stock_only_can_adjust_and_is_redirected_to_a_readable_item_page(): void
    {
        $this->role->update(['permissions' => ['credentials.stock.adjust']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->get(route('credentials.entitlements.show', $this->item, false).'?adjust=1&loc='.$location->id)
            ->assertInertia(fn (Assert $page) => $page->where('canEditItems', false)->where('canAdjustStock', true)
                ->where('is_read_only', false)->where('open_adjust', true)->where('adjust_location_id', $location->id));
        $payload = ['location_id' => $location->id, 'direction' => 'add', 'quantity' => 3];
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), $payload)
            ->assertRedirect(route('credentials.entitlements.show', $this->item));
        $this->get(route('credentials.entitlements.show', $this->item))->assertOk();
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), [...$payload, 'direction' => 'remove'])
            ->assertSessionHasErrors('reason');
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), [...$payload, 'direction' => 'remove', 'quantity' => 4, 'reason' => 'Damaged'])
            ->assertSessionHasErrors('quantity');
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), [...$payload, 'direction' => 'remove', 'quantity' => 1, 'reason' => 'Damaged'])
            ->assertSessionHasNoErrors();
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
        $this->delete(route('credentials.entitlements.destroy', [$this->event, $this->item]))->assertForbidden();
        $this->delete(route('credentials.passes.destroy', [$this->event, $this->pass]))->assertForbidden();
    }

    public function test_permissions_are_event_scoped_and_foreign_ids_are_refused(): void
    {
        $this->role->update(['permissions' => ['credentials.entitlements.edit', 'credentials.stock.adjust', 'credentials.passes.edit']]);
        $other = Event::create(['name' => 'Other festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $this->createTeamEngagement(['event_id' => $other->id, 'person_id' => $this->user->person_id, 'role_id' => $this->role->id, 'status' => 'hired']);
        $item = $other->entitlementItems()->create(['name' => 'Other item']);
        $pass = $other->passTypes()->create(['name' => 'Other pass']);
        $location = $other->locations()->create(['name' => 'Other location']);
        $label = $other->entitlementItemLabels()->create(['name' => 'Other label', 'color' => 'teal']);
        $this->get(route('credentials.entitlements.show', $item))->assertNotFound();
        $this->get(route('credentials.passes.edit', $pass))->assertNotFound();
        $this->put(route('credentials.entitlements.update', [$this->event, $item]), ['name' => 'Forbidden'])->assertNotFound();
        $this->post(route('credentials.entitlements.store', $other), ['name' => 'Forbidden'])->assertNotFound();
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Foreign opening', 'opening_balance' => 1, 'location_id' => $location->id])
            ->assertSessionHasErrors('location_id');
        $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), ['location_id' => $location->id, 'direction' => 'add', 'quantity' => 1])
            ->assertSessionHasErrors('location_id');
        $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), ['name' => 'Wristband', 'label_ids' => [$label->id]])
            ->assertSessionHasErrors('label_ids.0');
        $this->post(route('credentials.passes.store', $this->event), ['name' => 'Foreign items', 'entitlement_item_ids' => [$item->id]])
            ->assertSessionHasErrors('entitlement_item_ids.0');
    }

    public function test_item_delete_refuses_nonzero_stock_or_pass_references_and_pass_delete_refuses_assignments(): void
    {
        $this->role->update(['permissions' => ['credentials.entitlements.edit', 'credentials.passes.edit']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->seedStock($this->item, $location, 1, $this->user);
        $this->delete(route('credentials.entitlements.destroy', [$this->event, $this->item]))->assertSessionHasErrors('item');
        $this->seedStock($this->item, $location, -1, $this->user);
        $this->pass->entitlements()->create(['entitlement_item_id' => $this->item->id, 'sort_order' => 0]);
        $this->delete(route('credentials.entitlements.destroy', [$this->event, $this->item]))->assertSessionHasErrors('item');
        $patron = $this->event->patrons()->create(['person_id' => $this->user->person_id]);
        $this->pass->assignments()->create(['event_patron_id' => $patron->id, 'person_id' => $this->user->person_id]);
        $this->delete(route('credentials.passes.destroy', [$this->event, $this->pass]))->assertSessionHasErrors('pass_type');
        $this->assertDatabaseHas('entitlement_items', ['id' => $this->item->id]);
        $this->assertDatabaseHas('pass_types', ['id' => $this->pass->id]);
    }

    public function test_item_history_is_bounded_ordered_scoped_and_refuses_unbounded_requests(): void
    {
        $this->role->update(['permissions' => ['credentials.stock.adjust']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $ids = [];
        for ($index = 0; $index < 32; $index++) {
            $ids[] = $this->seedStock($this->item, $location, 1, $this->user)->id;
        }
        $other = $this->event->entitlementItems()->create(['name' => 'Other item']);
        $this->seedStock($other, $location, 1, $this->user);
        $params = ['entitlementItem' => $this->item, 'draw' => 4, 'start' => 0, 'length' => 10];
        $this->getJson(route('credentials.entitlements.history', $params))->assertOk()
            ->assertJsonPath('draw', 4)->assertJsonPath('recordsTotal', 32)->assertJsonPath('recordsFiltered', 32)
            ->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $ids[31])
            ->assertJsonPath('data.0.recorded_by', $this->user->name)->assertJsonMissingPath('data.0.actor.email');
        $this->getJson(route('credentials.entitlements.history', [...$params, 'start' => 30]))->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $ids[1]);
        foreach ([-1, 0, 25, 101] as $length) {
            $this->getJson(route('credentials.entitlements.history', [...$params, 'length' => $length]))->assertUnprocessable()->assertJsonValidationErrors('length');
        }
        $this->getJson(route('credentials.entitlements.history', [...$params, 'start' => -1]))->assertUnprocessable()->assertJsonValidationErrors('start');
        $this->role->update(['permissions' => []]);
        $this->getJson(route('credentials.entitlements.history', $params))->assertForbidden();
    }

    public function test_revocation_and_off_roles_apply_on_the_next_request(): void
    {
        $this->role->update(['permissions' => ['credentials.stock.adjust']]);
        $this->get(route('credentials.entitlements'))->assertOk();
        $this->role->update(['permissions' => []]);
        $this->get(route('credentials.entitlements'))->assertForbidden();
        $this->role->update(['permissions' => ['credentials.stock.adjust'], 'active' => false]);
        $this->get(route('credentials.entitlements'))->assertRedirect(route('login'));
    }

    public function test_locked_events_refuse_every_mutation_even_for_admins(): void
    {
        $this->role->update(['permissions' => ['credentials.entitlements.edit', 'credentials.stock.adjust', 'credentials.passes.edit']]);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->event->lock();
        foreach ([false, true] as $admin) {
            $this->user->forceFill(['is_admin' => $admin])->save();
            $this->get(route('credentials.passes'))->assertOk();
            $this->get(route('credentials.passes.edit', $this->pass))->assertInertia(fn (Assert $page) => $page
                ->component('Credentials/CreatePass')->where('canWrite', false));
            $this->get(route('credentials.passes.create'))->assertForbidden();
            $this->get(route('credentials.entitlements.show', $this->item, false).'?adjust=1')->assertInertia(fn (Assert $page) => $page
                ->where('is_read_only', true)->where('open_adjust', false));
            $this->post(route('credentials.passes.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
            $this->put(route('credentials.passes.update', [$this->event, $this->pass]), ['name' => 'Forbidden'])->assertForbidden();
            $this->delete(route('credentials.passes.destroy', [$this->event, $this->pass]))->assertForbidden();
            $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'Forbidden'])->assertForbidden();
            $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), ['name' => 'Forbidden'])->assertForbidden();
            $this->delete(route('credentials.entitlements.destroy', [$this->event, $this->item]))->assertForbidden();
            $this->post(route('credentials.entitlements.adjustments.store', [$this->event, $this->item]), [
                'location_id' => $location->id, 'direction' => 'add', 'quantity' => 1,
            ])->assertForbidden();
        }
    }

    public function test_admin_keeps_all_access_without_a_team_record(): void
    {
        $this->grantAdminAccess($this->user);
        TeamEngagement::where('person_id', $this->user->person_id)->delete();
        foreach (['credentials.passes', 'credentials.passes.create', 'credentials.entitlements', 'credentials.products'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('credentials.entitlements.show', $this->item))->assertInertia(fn (Assert $page) => $page
            ->where('canEditItems', true)->where('canAdjustStock', true));
    }
}
