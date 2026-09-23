<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Person;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArtistCheckInTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_list_contains_only_confirmed_current_event_artists_and_counts_held_entitlements(): void
    {
        [$user, $event] = $this->context();
        [$engagement, $person, $expected] = $this->heldEntitlement($event, 'River Hollow');
        $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $expected->entitlement_item_id,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);
        $unassigned = $engagement->passAssignments()->create([
            'pass_type_id' => $expected->passAssignment->pass_type_id,
        ]);
        $unassigned->expectedEntitlements()->create([
            'entitlement_item_id' => $expected->entitlement_item_id,
        ]);
        ArtistEngagement::factory()->for($event)->for(Artist::factory())->create(['status' => 'idea']);
        ArtistEngagement::factory()->for($this->event('Other'))->for(Artist::factory())->create(['status' => 'confirmed']);
        ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => 'Zero Expected']))->create(['status' => 'confirmed']);

        $this->actingAs($user)->get(route('artists.check-in'))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/CheckIn')
            ->has('engagements', 2)
            ->where('engagements.0.name', 'River Hollow')
            ->where('engagements.0.contact.name', $person->name)
            ->where('engagements.0.issued', 1)
            ->where('engagements.0.expected', 1)
            ->where('engagements.0.check_in_status', 'complete')
            ->where('engagements.1.name', 'Zero Expected')
            ->where('engagements.1.check_in_status', 'complete'));
    }

    public function test_consume_records_location_and_decrements_only_that_location(): void
    {
        [$user, $event] = $this->context();
        [$engagement, , $expected] = $this->heldEntitlement($event, 'River Hollow');
        $main = $event->locations()->create(['name' => 'Main stage']);
        $other = $event->locations()->create(['name' => 'Box office']);
        $item = $expected->entitlementItem;
        $item->adjustments()->create(['location_id' => $main->id, 'delta' => 2, 'user_id' => $user->id]);
        $item->adjustments()->create(['location_id' => $other->id, 'delta' => 4, 'user_id' => $user->id]);

        $this->actingAs($user)->post(route('artists.check-in.issues.store', $expected), [
            'location_id' => $main->id,
            'code' => ' AW-10482 ',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('issued_entitlements', [
            'expected_entitlement_id' => $expected->id,
            'location_id' => $main->id,
            'code' => 'AW-10482',
            'issued_by' => $user->id,
        ]);
        $this->assertSame(ExpectedEntitlement::STATUS_CONSUMED, $expected->fresh()->status);
        $this->assertSame(1, (int) $item->adjustments()->where('location_id', $main->id)->sum('delta'));
        $this->assertSame(4, (int) $item->adjustments()->where('location_id', $other->id)->sum('delta'));
    }

    public function test_consume_page_lists_people_alphabetically_and_only_their_held_entitlements(): void
    {
        [$user, $event] = $this->context();
        [$engagement, $maya, $expected] = $this->heldEntitlement($event, 'River Hollow', 'Maya Chen');
        $riley = Person::query()->create(['name' => 'Riley Quinn', 'email' => 'riley@example.com']);
        $engagement->people()->attach($riley);
        $location = $event->locations()->create(['name' => 'Main stage']);
        $expected->entitlementItem->adjustments()->create([
            'location_id' => $location->id,
            'delta' => 2,
            'user_id' => $user->id,
        ]);
        $unassigned = $engagement->passAssignments()->create([
            'pass_type_id' => $expected->passAssignment->pass_type_id,
        ]);
        $unassigned->expectedEntitlements()->create([
            'entitlement_item_id' => $expected->entitlement_item_id,
        ]);

        $this->actingAs($user)->get(route('artists.check-in.show', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/CheckInShow')
            ->where('engagement.name', 'River Hollow')
            ->has('engagement.people', 2)
            ->where('engagement.people.0.name', $maya->name)
            ->where('engagement.people.0.expected', 1)
            ->where('engagement.people.0.entitlements.0.status', 'pending')
            ->where('engagement.people.0.entitlements.0.locations.0.name', 'Main stage')
            ->where('engagement.people.1.name', $riley->name)
            ->where('engagement.people.1.expected', 0));
    }

    public function test_consume_rejects_invalid_stock_event_and_lock_states(): void
    {
        [$user, $event] = $this->context();
        [, , $expected] = $this->heldEntitlement($event, 'River Hollow');
        $location = $event->locations()->create(['name' => 'Empty']);

        $this->actingAs($user)->post(route('artists.check-in.issues.store', $expected), [])
            ->assertSessionHasErrors('location_id');

        $this->post(route('artists.check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertSessionHasErrors('quantity');

        $otherLocation = $this->event('Other')->locations()->create(['name' => 'Other']);
        $this->post(route('artists.check-in.issues.store', $expected), [
            'location_id' => $otherLocation->id,
        ])->assertSessionHasErrors('location_id');

        $event->lock();
        $this->post(route('artists.check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertForbidden();
    }

    /** @return array{User, Event} */
    private function context(): array
    {
        $user = User::factory()->create();
        $event = $this->event('Sunrise Folk Fest 2026');
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }

    /** @return array{ArtistEngagement, Person, ExpectedEntitlement} */
    private function heldEntitlement(Event $event, string $artistName, string $personName = 'Maya Chen'): array
    {
        $engagement = ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => $artistName]))->create(['status' => 'confirmed']);
        $person = Person::query()->create(['name' => $personName, 'email' => str($personName)->slug().'@example.com']);
        $engagement->people()->attach($person, ['is_primary' => true]);
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $pass = $event->passTypes()->create(['name' => 'Artist pass']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $pass->id,
            'person_id' => $person->id,
        ]);
        $expected = $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
            'status' => ExpectedEntitlement::STATUS_EXPECTED,
        ]);

        return [$engagement, $person, $expected];
    }

    private function event(string $name): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
    }
}
