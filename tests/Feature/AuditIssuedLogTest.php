<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditIssuedLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_issued_history_is_paged_filtered_and_scoped(): void
    {
        $this->withoutVite();
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);
        $owner = ArtistEngagement::factory()->for($event)->create();
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $owner->passAssignments()->create(['pass_type_id' => $pass->id]);
        $location = $event->locations()->create(['name' => 'Gate']);
        foreach (range(1, 26) as $number) {
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
            $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $location->id, 'code' => 'CODE-'.$number, 'issued_by' => $user->id, 'issued_at' => '2027-06-01 10:00:00']);
        }
        $url = route('credentials.entitlements.issued', $item);
        $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('recordsTotal', 26)->assertJsonCount(25, 'data')->assertJsonPath('data.0.code', 'CODE-26');
        $this->getJson($url.'?start=25&length=25&draw=4')->assertJsonPath('draw', 4)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'CODE-1');
        $this->getJson(route('credentials.entitlements.issued', [$item, 'search' => ['value' => 'CODE-26']]))->assertJsonPath('recordsTotal', 26)->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data');
        $this->getJson($url.'?length=-1')->assertUnprocessable()->assertJsonValidationErrors('length');
        $other = Event::create(['name' => 'Other', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $foreign = $other->entitlementItems()->create(['name' => 'Foreign']);
        $this->getJson(route('credentials.entitlements.issued', $foreign))->assertNotFound();
        $event->lock();
        $this->getJson($url)->assertOk();
        $this->grantRoleAccess($user, ['team.view']);
        $this->getJson($url)->assertForbidden();
    }
}
