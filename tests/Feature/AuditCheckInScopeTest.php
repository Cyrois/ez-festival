<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditCheckInScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_code_search_cannot_match_a_holder_from_another_event(): void
    {
        [$user, $event] = $this->context();
        $other = Event::create(['name' => 'Other', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $person = Person::create(['name' => 'Same Holder', 'email' => 'same-holder@example.test']);
        foreach ([$event, $other] as $scope) {
            $owner = ArtistEngagement::factory()->for($scope)->create(['status' => 'confirmed']);
            $owner->people()->attach($person);
            $pass = $scope->passTypes()->create(['name' => 'Artist']);
            $item = $scope->entitlementItems()->create(['name' => 'Wristband']);
            $location = $scope->locations()->create(['name' => 'Gate']);
            $assignment = $owner->passAssignments()->create(['person_id' => $person->id, 'pass_type_id' => $pass->id]);
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
            if ($scope->is($other)) {
                $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $location->id, 'code' => 'OTHER-EVENT-CODE', 'issued_by' => $user->id, 'issued_at' => now()]);
            }
        }
        $this->actingAs($user)->get(route('check-in.index', ['search' => 'OTHER-EVENT-CODE']))
            ->assertInertia(fn (Assert $page) => $page->has('people.data', 0));
        $this->get(route('check-in.index', ['search' => 'Same Holder']))
            ->assertInertia(fn (Assert $page) => $page->has('people.data', 1)->where('people.data.0.expected', 1)->where('people.data.0.issued', 0));
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
