<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\PassType;
use App\Models\PassTypeLabel;
use App\Models\User;
use App\Services\PassAssignmentService;
use App\Support\OrganizationContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PassTypesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_catalog_uses_its_own_pass_type_label_taxonomy(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = PassTypeLabel::query()->create(['name' => 'All access', 'color' => 'teal']);
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $passType->labels()->attach($label);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Passes')
                ->where('labels.0.id', $label->id)
                ->where('passes.0.labels.0.id', $label->id),
        );
    }

    public function test_catalog_is_event_scoped_and_repeat_load_uses_the_cached_list(): void
    {
        [$user, $event] = $this->createEventContext();
        $event->passTypes()->create(['name' => 'Current pass']);
        $otherEvent = Event::query()->create([
            'name' => 'Other festival',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-12',
            'timezone' => 'America/Vancouver',
        ]);
        $otherEvent->passTypes()->create(['name' => 'Foreign pass']);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->has('passes', 1)
                ->where('passes.0.name', 'Current pass'),
        );

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('credentials.passes'))->assertOk();

        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'pass_types'),
        ));
    }

    public function test_every_pass_list_write_clears_the_cache(): void
    {
        [$user, $event] = $this->createEventContext();
        $this->actingAs($user)->get(route('credentials.passes'))->assertOk();

        $this->post(route('credentials.passes.store', $event), [
            'name' => 'Artist pass',
            'new_labels' => [['name' => 'Backstage', 'color' => 'teal']],
        ])->assertRedirect(route('credentials.passes'));
        $passType = PassType::query()->where('name', 'Artist pass')->sole();
        $this->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->where('passes.0.name', 'Artist pass')
                ->where('passes.0.labels.0.name', 'Backstage'),
        );

        $this->put(route('credentials.passes.update', [$event, $passType]), [
            'name' => 'Production pass',
        ])->assertRedirect(route('credentials.passes'));
        $this->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page->where('passes.0.name', 'Production pass'),
        );

        $this->delete(route('credentials.passes.destroy', [$event, $passType]))
            ->assertRedirect(route('credentials.passes'));
        $this->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page->has('passes', 0),
        );
    }

    public function test_assignment_writes_clear_the_cached_pass_usage_count(): void
    {
        [$user, $event] = $this->createEventContext();
        $passType = $event->passTypes()->create(['name' => 'Artist pass']);
        $artist = Artist::query()->create(['name' => 'The Headliners']);
        $engagement = $artist->engagements()->create(['event_id' => $event->id]);
        $assignments = app(PassAssignmentService::class);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page->where('passes.0.assigned_count', 0),
        );

        $assignments->give($engagement, $passType, 1);
        $this->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page->where('passes.0.assigned_count', 1),
        );

        $assignments->remove($passType->assignments()->sole());
        $this->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page->where('passes.0.assigned_count', 0),
        );
    }

    public function test_create_persists_custom_fields_and_real_entitlement_lines(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = PassTypeLabel::query()->create(['name' => 'Backstage', 'color' => 'soft_blue']);
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_PASS,
            'label' => 'Print name',
            'key' => 'print_name',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->actingAs($user)->post(route('credentials.passes.store', $event), [
            'name' => 'Artist',
            'max_assignments' => 50,
            'label_ids' => [$label->id],
            'entitlement_item_ids' => [$item->id, $item->id],
            'custom_fields' => [$field->id => 'Artist name'],
        ])->assertRedirect(route('credentials.passes'));

        $passType = PassType::query()->sole();
        $this->assertSame(2, $passType->entitlements()->count());
        $this->assertSame(50, $passType->max_assignments);
        $this->assertTrue($passType->labels()->whereKey($label)->exists());
        $this->assertDatabaseHas('custom_field_values', [
            'custom_fieldable_type' => PassType::class,
            'custom_fieldable_id' => $passType->id,
            'value_text' => 'Artist name',
        ]);
    }

    public function test_create_accepts_a_new_label_from_the_locked_palette(): void
    {
        [$user, $event] = $this->createEventContext();

        $this->actingAs($user)->post(route('credentials.passes.store', $event), [
            'name' => 'Production',
            'new_labels' => [['name' => 'Crew', 'color' => 'violet']],
        ])->assertRedirect(route('credentials.passes'));

        $this->assertDatabaseHas('pass_type_labels', [
            'name_key' => 'crew',
            'color' => 'violet',
        ]);
        $this->assertSame('Crew', PassType::query()->sole()->labels()->sole()->name);
    }

    public function test_edit_loads_and_replaces_entitlement_lines(): void
    {
        [$user, $event] = $this->createEventContext();
        $first = $event->entitlementItems()->create(['name' => 'Wristband']);
        $second = $event->entitlementItems()->create(['name' => 'Meal voucher']);
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $passType->entitlements()->create(['entitlement_item_id' => $first->id, 'sort_order' => 0]);

        $this->actingAs($user)->get(route('credentials.passes.edit', $passType))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/CreatePass')
                ->where('pass.entitlement_item_ids.0', $first->id),
        );

        $this->actingAs($user)->put(route('credentials.passes.update', [$event, $passType]), [
            'name' => 'Artist Plus',
            'max_assignments' => null,
            'entitlement_item_ids' => [$second->id, $second->id],
        ])->assertRedirect(route('credentials.passes'));

        $this->assertSame([$second->id, $second->id], $passType->fresh()->entitlements()->orderBy('sort_order')->pluck('entitlement_item_id')->all());
    }

    public function test_pass_type_with_assignments_cannot_be_deleted(): void
    {
        [$user, $event] = $this->createEventContext();
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $artist = Artist::query()->create(['name' => 'The Headliners']);
        $engagement = $artist->engagements()->create(['event_id' => $event->id]);
        $passType->assignments()->create(['artist_engagement_id' => $engagement->id]);

        $this->actingAs($user)
            ->delete(route('credentials.passes.destroy', [$event, $passType]))
            ->assertSessionHasErrors('pass_type');
    }

    public function test_destroy_without_manage_credentials_is_unauthorized(): void
    {
        [$user, $event] = $this->createEventContext();
        $passType = $event->passTypes()->create(['name' => 'Artist']);

        Gate::define('manage-credentials', fn (): bool => false);

        $this->actingAs($user)
            ->delete(route('credentials.passes.destroy', [$event, $passType]))
            ->assertForbidden();

        $this->assertDatabaseHas('pass_types', ['id' => $passType->id]);
    }

    public function test_entitlement_item_ids_must_belong_to_the_route_event(): void
    {
        [$user, $event] = $this->createEventContext();
        $other = Event::query()->create([
            'name' => 'Other Fest',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-02',
            'timezone' => 'America/Vancouver',
        ]);
        $foreign = $other->entitlementItems()->create(['name' => 'Foreign wristband']);

        $this->actingAs($user)->post(route('credentials.passes.store', $event), [
            'name' => 'Artist',
            'entitlement_item_ids' => [$foreign->id],
        ])->assertSessionHasErrors('entitlement_item_ids.0');
    }

    /** @return array{User, Event} */
    private function createEventContext(): array
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);
        $this->grantAdminAccess($user);

        return [$user, $event];
    }
}
