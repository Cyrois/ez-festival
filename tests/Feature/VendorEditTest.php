<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Models\VendorEngagementNote;
use App\Models\VendorType;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VendorEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_edit_page_contains_vendor_details_and_notes(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::query()->create(['name' => 'North Catering', 'name_key' => 'north catering']);
        $engagement = VendorEngagement::query()->create([
            'vendor_id' => $vendor->id,
            'event_id' => $event->id,
            'status' => 'outreach',
        ]);
        VendorEngagementNote::factory()->for($engagement, 'engagement')->for($user)->create(['body' => 'Confirm power needs.']);

        $this->actingAs($user)->get(route('vendors.view', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Vendors/View')
            ->where('engagement.name', 'North Catering')
            ->where('engagement.status', 'outreach')
            ->has('notes', 1)
            ->where('notes.0.body', 'Confirm power needs.')
            ->where('canWrite', true));
    }

    public function test_vendor_update_saves_staged_notes_with_the_vendor(): void
    {
        [$user, $event] = $this->context();
        $type = VendorType::query()->create(['name' => 'Food']);
        $vendor = Vendor::query()->create(['name' => 'North Catering', 'name_key' => 'north catering']);
        $engagement = VendorEngagement::query()->create(['vendor_id' => $vendor->id, 'event_id' => $event->id]);

        $this->actingAs($user)->put(route('vendors.update', $engagement), [
            'name' => ' North Catering Updated ',
            'status' => 'confirmed',
            'vendor_type_id' => $type->id,
            'notes' => [['body' => '  Booth confirmed. ']],
        ])->assertRedirect(route('vendors.view', $engagement));

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'name' => 'North Catering Updated', 'name_key' => 'north catering updated']);
        $this->assertDatabaseHas('vendor_engagements', ['id' => $engagement->id, 'status' => 'confirmed', 'vendor_type_id' => $type->id]);

        $this->assertDatabaseHas('vendor_engagement_notes', ['vendor_engagement_id' => $engagement->id, 'body' => 'Booth confirmed.']);
    }

    public function test_locked_event_blocks_vendor_update(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::query()->create(['name' => 'North Catering', 'name_key' => 'north catering']);
        $engagement = VendorEngagement::query()->create(['vendor_id' => $vendor->id, 'event_id' => $event->id]);
        $event->lock();

        $this->actingAs($user)->put(route('vendors.update', $engagement), ['name' => 'Changed', 'status' => 'confirmed'])->assertForbidden();
        $this->assertDatabaseCount('vendor_engagement_notes', 0);
    }

    /** @return array{0: User, 1: Event} */
    private function context(): array
    {
        $user = User::factory()->create();
        $event = Event::query()->create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
