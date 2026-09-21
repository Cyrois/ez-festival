<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\EventPatron;
use App\Models\ExpectedEntitlement;
use App\Models\Person;
use App\Models\User;
use App\Services\EntitlementConsumeService;
use App\Services\PassAssignmentService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PassAssignmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_new_user_has_a_person_profile(): void
    {
        $user = User::factory()->create(['name' => 'Alex Smith', 'email' => 'alex@example.com']);

        $this->assertDatabaseHas('people', [
            'id' => $user->person_id,
            'name' => 'Alex Smith',
            'email' => 'alex@example.com',
        ]);
    }

    public function test_give_creates_directly_owned_assignments_and_expected_entitlements(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $passType = $event->passTypes()->create(['name' => 'Artist', 'max_assignments' => 2]);
        $passType->entitlements()->create(['entitlement_item_id' => $item->id, 'sort_order' => 0]);

        $this->actingAs($user)->post(route('artists.pass-assignments.store', $engagement), [
            'pass_type_id' => $passType->id,
            'quantity' => 2,
        ])->assertSessionHas('success', 'Passes given.');

        $this->assertDatabaseCount('pass_assignments', 2);
        $this->assertDatabaseHas('pass_assignments', [
            'pass_type_id' => $passType->id,
            'artist_engagement_id' => $engagement->id,
            'person_id' => null,
        ]);
        $this->assertDatabaseCount('expected_entitlements', 2);

        $this->actingAs($user)->post(route('artists.pass-assignments.store', $engagement), [
            'pass_type_id' => $passType->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('quantity');
    }

    public function test_consume_marks_expected_row_issued_and_writes_negative_adjustment(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $item->adjustments()->create(['delta' => 1]);
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $passType->id]);
        $expected = $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
            'status' => ExpectedEntitlement::STATUS_EXPECTED,
        ]);

        app(EntitlementConsumeService::class)->consume($expected, $user, 'RFID-1');

        $this->assertDatabaseHas('issued_entitlements', [
            'expected_entitlement_id' => $expected->id,
            'entitlement_item_id' => $item->id,
            'code' => 'RFID-1',
        ]);
        $this->assertSame(ExpectedEntitlement::STATUS_CONSUMED, $expected->fresh()->status);
        $this->assertSame(0, $item->adjustments()->sum('delta'));
    }

    public function test_assignment_with_issued_entitlement_cannot_be_removed(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $passType->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $item->id,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('pass-assignments.destroy', $assignment))
            ->assertSessionHasErrors('assignment');
    }

    public function test_assignment_requires_a_person_linked_to_its_artist_engagement(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $passType->id]);
        $person = Person::query()->create(['name' => 'Not linked', 'email' => 'not-linked@example.com']);

        $this->actingAs($user)
            ->put(route('pass-assignments.update', $assignment), ['person_id' => $person->id])
            ->assertStatus(422);
    }

    public function test_an_event_patron_can_own_a_pass_assignment(): void
    {
        [$user, $event] = $this->artistContext();
        $patron = EventPatron::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
        ]);
        $passType = $event->passTypes()->create(['name' => 'Patron']);

        app(PassAssignmentService::class)->give($patron, $passType, 1);

        $this->assertDatabaseHas('pass_assignments', [
            'pass_type_id' => $passType->id,
            'event_patron_id' => $patron->id,
        ]);
    }

    /** @return array{User, Event, ArtistEngagement} */
    private function artistContext(): array
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

        $artist = Artist::query()->create(['name' => 'The Headliners']);
        $engagement = ArtistEngagement::query()->create(['artist_id' => $artist->id, 'event_id' => $event->id]);

        return [$user, $event, $engagement];
    }
}
