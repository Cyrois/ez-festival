<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
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

    public function test_vendor_list_returns_the_complete_current_event_collection(): void
    {
        [$user, $event] = $this->context();

        foreach (range(1, 30) as $index) {
            $name = "Vendor {$index}";
            $vendor = Vendor::query()->create([
                'name' => $name,
                'name_key' => mb_strtolower($name),
            ]);
            VendorEngagement::query()->create([
                'vendor_id' => $vendor->id,
                'event_id' => $event->id,
                'status' => 'idea',
            ]);
        }

        $otherEvent = $this->event('Other Festival');
        $otherVendor = Vendor::query()->create([
            'name' => 'Other Event Vendor',
            'name_key' => 'other event vendor',
        ]);
        VendorEngagement::query()->create([
            'vendor_id' => $otherVendor->id,
            'event_id' => $otherEvent->id,
            'status' => 'idea',
        ]);

        $this->actingAs($user)->get(route('vendors.advancing'))->assertInertia(fn (Assert $page) => $page
            ->has('vendors.data', 30)
            ->missing('vendors.meta')
            ->where('event.id', $event->id));
    }

    public function test_can_create_a_vendor_with_custom_field_values(): void
    {
        [$user, $event] = $this->context();
        $field = CustomField::query()->create([
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

    public function test_vendor_custom_field_values_are_scoped_per_event(): void
    {
        [$user, $eventA] = $this->context();
        $eventB = Event::query()->create([
            'name' => 'Second Festival',
            'starts_on' => '2027-08-01',
            'ends_on' => '2027-08-03',
            'timezone' => 'America/Vancouver',
        ]);

        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_VENDOR,
            'label' => 'Booth size',
            'key' => 'booth_size',
            'type' => 'text',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post(route('vendors.store', $eventA), [
            'name' => 'North Catering',
            'custom_fields' => [$field->id => '10x10'],
        ])->assertRedirect(route('vendors.advancing'));

        $user->setCurrentEvent($eventB);

        $this->actingAs($user)->post(route('vendors.store', $eventB), [
            'name' => 'North Catering',
            'custom_fields' => [$field->id => '20x20'],
        ])->assertRedirect(route('vendors.advancing'));

        $vendor = Vendor::query()->where('name', 'North Catering')->firstOrFail();

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'custom_fieldable_type' => Vendor::class,
            'custom_fieldable_id' => $vendor->id,
            'event_id' => $eventA->id,
            'value_text' => '10x10',
        ]);
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'custom_fieldable_type' => Vendor::class,
            'custom_fieldable_id' => $vendor->id,
            'event_id' => $eventB->id,
            'value_text' => '20x20',
        ]);
        $this->assertSame(2, $vendor->customFieldValues()->count());
    }

    public function test_deleting_events_cascades_vendor_custom_field_values_without_unique_collision(): void
    {
        [$user, $eventA] = $this->context();
        $eventB = Event::query()->create([
            'name' => 'Second Festival',
            'starts_on' => '2027-08-01',
            'ends_on' => '2027-08-03',
            'timezone' => 'America/Vancouver',
        ]);

        $vendorField = CustomField::query()->create([
            'target' => CustomField::TARGET_VENDOR,
            'label' => 'Booth size',
            'key' => 'booth_size',
            'type' => 'text',
            'sort_order' => 1,
        ]);
        $userField = CustomField::query()->create([
            'target' => CustomField::TARGET_USER,
            'label' => 'Shirt size',
            'key' => 'shirt_size',
            'type' => 'text',
            'sort_order' => 1,
        ]);

        CustomFieldValue::query()->create([
            'custom_field_id' => $userField->id,
            'event_id' => null,
            'custom_fieldable_type' => User::class,
            'custom_fieldable_id' => $user->id,
            'value_text' => 'M',
            'value_search' => 'm',
        ]);

        $this->actingAs($user)->post(route('vendors.store', $eventA), [
            'name' => 'North Catering',
            'custom_fields' => [$vendorField->id => '10x10'],
        ])->assertRedirect(route('vendors.advancing'));

        $user->setCurrentEvent($eventB);

        $this->actingAs($user)->post(route('vendors.store', $eventB), [
            'name' => 'North Catering',
            'custom_fields' => [$vendorField->id => '20x20'],
        ])->assertRedirect(route('vendors.advancing'));

        $vendor = Vendor::query()->where('name', 'North Catering')->firstOrFail();
        $this->assertSame(2, $vendor->customFieldValues()->count());

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $eventA))
            ->assertRedirect(route('settings.events.index'));
        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $eventB))
            ->assertRedirect(route('settings.events.index'));

        $this->assertDatabaseMissing('custom_field_values', [
            'custom_field_id' => $vendorField->id,
            'custom_fieldable_type' => Vendor::class,
            'custom_fieldable_id' => $vendor->id,
        ]);
        $this->assertSame(0, $vendor->customFieldValues()->count());
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $userField->id,
            'event_id' => null,
            'custom_fieldable_type' => User::class,
            'custom_fieldable_id' => $user->id,
            'value_text' => 'M',
        ]);
    }

    public function test_vendor_update_syncs_custom_fields(): void
    {
        [$user, $event] = $this->context();
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_VENDOR,
            'label' => 'Power needs',
            'key' => 'power_needs',
            'type' => 'text',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post(route('vendors.store', $event), [
            'name' => 'North Catering',
            'custom_fields' => [$field->id => '120V'],
        ])->assertRedirect();

        $engagement = VendorEngagement::query()->firstOrFail();

        $this->actingAs($user)->put(route('vendors.update', $engagement), [
            'name' => 'North Catering',
            'status' => 'confirmed',
            'custom_fields' => [$field->id => '240V'],
        ])->assertRedirect(route('vendors.view', $engagement));

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'event_id' => $event->id,
            'value_text' => '240V',
        ]);
    }
}
