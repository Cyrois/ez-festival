<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Models\VendorType;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VendorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $event = $this->event('Festival');

        $this->post(route('vendors.store', $event), [
            'name' => 'North Catering',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_can_create_vendor_and_see_it_in_the_list(): void
    {
        [$user, $event] = $this->context();
        $type = VendorType::query()->create(['name' => 'Food']);

        $this->actingAs($user)->post(route('vendors.store', $event), [
            'name' => '  North Catering  ',
            'status' => 'confirmed',
            'vendor_type_id' => $type->id,
        ])->assertRedirect(route('vendors.advancing'))
            ->assertSessionHas('success', __('vendors.toast.created'));

        $vendor = Vendor::query()->firstOrFail();
        $this->assertSame('North Catering', $vendor->name);
        $this->assertSame('north catering', $vendor->name_key);
        $engagement = $vendor->engagements()->firstOrFail();
        $this->assertSame('confirmed', $engagement->status);
        $this->assertSame($type->id, $engagement->vendor_type_id);
        $this->assertSame($event->id, $engagement->event_id);

        $this->actingAs($user)->get(route('vendors.advancing'))->assertInertia(fn (Assert $page) => $page
            ->component('Vendors/Index')
            ->where('vendors.data.0.name', 'North Catering')
            ->where('vendors.data.0.status', 'confirmed')
            ->where('vendors.data.0.type', 'Food')
            ->where('event.id', $event->id));
    }

    public function test_duplicate_vendor_names_are_rejected_for_the_same_event(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::query()->create([
            'name' => 'North Catering',
            'name_key' => 'north catering',
        ]);
        VendorEngagement::query()->create([
            'vendor_id' => $vendor->id,
            'event_id' => $event->id,
            'status' => 'idea',
        ]);

        $this->actingAs($user)->from(route('vendors.create'))->post(route('vendors.store', $event), [
            'name' => ' north catering ',
        ])->assertRedirect(route('vendors.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('vendor_engagements', 1);
    }

    public function test_can_create_a_vendor_with_custom_field_values(): void
    {
        [$user, $event] = $this->context();
        $field = CustomField::query()->create([
            'organization_id' => Organization::query()->firstOrFail()->id,
            'target' => CustomField::TARGET_VENDOR,
            'label' => 'Wristband provider',
            'key' => 'wristband_provider',
            'type' => 'select',
            'options' => ['Yes', 'No'],
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post(route('vendors.store', $event), [
            'name' => 'North Catering',
            'custom_fields' => [$field->id => 'Yes'],
        ])->assertRedirect(route('vendors.advancing'));

        $vendor = Vendor::query()->firstOrFail();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'custom_fieldable_type' => Vendor::class,
            'custom_fieldable_id' => $vendor->id,
            'value_text' => 'Yes',
            'value_search' => 'yes',
        ]);
    }

    public function test_existing_global_vendor_can_be_added_to_another_event(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::query()->create([
            'name' => 'North Catering',
            'name_key' => 'north catering',
        ]);

        $this->actingAs($user)->post(route('vendors.store', $event), [
            'name' => 'North Catering',
            'status' => 'outreach',
        ])->assertRedirect(route('vendors.advancing'));

        $this->assertSame(1, Vendor::query()->count());
        $this->assertSame(1, VendorEngagement::query()->count());
        $this->assertSame($vendor->id, VendorEngagement::query()->firstOrFail()->vendor_id);
    }

    public function test_locked_event_blocks_vendor_creation(): void
    {
        [$user, $event] = $this->context();
        $event->lock();

        $this->actingAs($user)->post(route('vendors.store', $event), [
            'name' => 'North Catering',
        ])->assertForbidden();

        $this->assertDatabaseCount('vendors', 0);
    }

    /** @return array{0: User, 1: Event} */
    private function context(): array
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
}
