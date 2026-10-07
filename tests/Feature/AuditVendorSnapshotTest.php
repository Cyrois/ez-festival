<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Services\PassAssignmentService;
use App\Services\PassTypeService;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditVendorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_vendor_save_keeps_nonempty_snapshot_row_identity(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::create(['name' => 'Snapshot Vendor']);
        $owner = VendorEngagement::create(['vendor_id' => $vendor->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        $oldItem = $event->entitlementItems()->create(['name' => 'Old entitlement']);
        $newItem = $event->entitlementItems()->create(['name' => 'New entitlement']);
        $pass = $event->passTypes()->create(['name' => 'Vendor']);
        app(PassTypeService::class)->update($pass, ['name' => $pass->name, 'entitlement_item_ids' => [$oldItem->id]], new Collection);
        app(PassAssignmentService::class)->give($owner, $pass, 1);
        $assignment = $owner->passAssignments()->sole();
        $snapshot = $assignment->expectedEntitlements()->sole();
        app(PassTypeService::class)->update($pass, ['name' => $pass->name, 'entitlement_item_ids' => [$newItem->id]], new Collection);
        $this->actingAs($user)->put(route('vendors.update', $owner), [
            'name' => 'Renamed Vendor', 'status' => 'confirmed',
            'pass_assignments' => [['id' => $assignment->id, 'pass_type_id' => $pass->id, 'person_id' => null]],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$snapshot->id], $assignment->expectedEntitlements()->pluck('id')->all());
        $this->assertSame($oldItem->id, $snapshot->fresh()->entitlement_item_id);
        $this->assertSame('Renamed Vendor', $vendor->fresh()->name);
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Database Regression', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
