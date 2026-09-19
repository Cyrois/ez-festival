<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PassAssignmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_new_user_has_a_person_profile(): void
    {
        $user = User::factory()->create(['name' => 'Alex Smith', 'email' => 'alex@example.com']);

        $this->assertNotNull($user->person_id);
        $this->assertDatabaseHas('people', [
            'id' => $user->person_id,
            'name' => 'Alex Smith',
            'email' => 'alex@example.com',
        ]);
    }

    public function test_giving_passes_creates_unassigned_rows_and_enforces_capacity(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $pass = $event->passes()->create(['name' => 'Artist', 'max_assignments' => 2]);

        $this->actingAs($user)
            ->post(route('artists.pass-assignments.store', $engagement), ['pass_id' => $pass->id, 'quantity' => 2])
            ->assertSessionHas('success', 'Passes given.');

        $this->assertDatabaseCount('pass_assignments', 2);
        $this->assertDatabaseHas('pass_assignments', ['pass_id' => $pass->id, 'person_id' => null]);

        $this->actingAs($user)
            ->post(route('artists.pass-assignments.store', $engagement), ['pass_id' => $pass->id, 'quantity' => 1])
            ->assertSessionHasErrors('quantity');
    }

    public function test_assignment_can_only_be_given_for_the_engagement_event(): void
    {
        [$user, , $engagement] = $this->artistContext();
        $otherEvent = Event::query()->create([
            'name' => 'Other Event',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $pass = $otherEvent->passes()->create(['name' => 'Other']);

        $this->actingAs($user)
            ->post(route('artists.pass-assignments.store', $engagement), ['pass_id' => $pass->id, 'quantity' => 1])
            ->assertNotFound();
    }

    public function test_artist_contacts_are_multi_value_with_one_primary_and_assignments_use_them(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $pass = $event->passes()->create(['name' => 'Artist']);

        $this->actingAs($user)
            ->post(route('artists.people.store', $engagement), ['name' => 'Taylor', 'email' => 'taylor@example.com'])
            ->assertSessionHas('success', 'Contact added.');
        $this->actingAs($user)
            ->post(route('artists.people.store', $engagement), ['name' => 'Morgan'])
            ->assertSessionHas('success', 'Contact added.');

        $people = $engagement->people()->orderBy('people.id')->get();
        $this->assertTrue((bool) $people->first()->pivot->is_primary);
        $this->assertFalse((bool) $people->last()->pivot->is_primary);

        $this->actingAs($user)
            ->put(route('artists.people.update', [$engagement, $people->last()]), [
                'name' => 'Morgan',
                'email' => null,
                'phone' => null,
                'is_primary' => true,
            ])
            ->assertSessionHas('success', 'Contact updated.');

        $this->assertSame(1, $engagement->people()->wherePivot('is_primary', true)->count());

        $assignment = $engagement->passAssignments()->create(['pass_id' => $pass->id]);
        $this->actingAs($user)
            ->put(route('pass-assignments.update', $assignment), ['person_id' => $people->last()->id])
            ->assertSessionHas('success', 'Pass assigned.');

        $this->assertDatabaseHas('pass_assignments', ['id' => $assignment->id, 'person_id' => $people->last()->id]);
    }

    public function test_assignment_cannot_use_a_person_not_linked_to_the_engagement(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $assignment = $engagement->passAssignments()->create([
            'pass_id' => $event->passes()->create(['name' => 'Artist'])->id,
        ]);
        $person = Person::query()->create(['name' => 'Not linked']);

        $this->actingAs($user)
            ->put(route('pass-assignments.update', $assignment), ['person_id' => $person->id])
            ->assertStatus(422);
    }

    public function test_removing_an_assignment_hard_deletes_it(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $assignment = $engagement->passAssignments()->create([
            'pass_id' => $event->passes()->create(['name' => 'Artist'])->id,
        ]);

        $this->actingAs($user)
            ->delete(route('pass-assignments.destroy', $assignment))
            ->assertSessionHas('success', 'Pass removed.');

        $this->assertDatabaseMissing('pass_assignments', ['id' => $assignment->id]);
    }

    public function test_locked_event_rejects_assignment_writes(): void
    {
        [$user, $event, $engagement] = $this->artistContext();
        $pass = $event->passes()->create(['name' => 'Artist']);
        $event->lock();

        $this->actingAs($user)
            ->post(route('artists.pass-assignments.store', $engagement), ['pass_id' => $pass->id, 'quantity' => 1])
            ->assertForbidden();

        $this->assertDatabaseCount('pass_assignments', 0);
    }

    /**
     * @return array{User, Event, ArtistEngagement}
     */
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
