<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
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
        $this->assertSame('confirmed', $vendor->status);
        $this->assertSame($type->id, $vendor->vendor_type_id);

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
        Vendor::query()->create([
            'event_id' => $event->id,
            'name' => 'North Catering',
            'name_key' => 'north catering',
            'status' => 'idea',
        ]);

        $this->actingAs($user)->from(route('vendors.create'))->post(route('vendors.store', $event), [
            'name' => ' north catering ',
        ])->assertRedirect(route('vendors.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('vendors', 1);
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
