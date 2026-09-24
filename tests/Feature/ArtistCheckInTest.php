<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\PassTypeLabel;
use App\Models\Person;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
        $zero = ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => 'Zero Expected']))->create(['status' => 'confirmed']);
        $zeroPerson = Person::query()->create(['name' => 'Alex Kim', 'email' => 'alex@example.com']);
        $zero->people()->attach($zeroPerson);
        $zero->passAssignments()->create([
            'pass_type_id' => $expected->passAssignment->pass_type_id,
            'person_id' => $zeroPerson->id,
        ]);

        $this->actingAs($user)->get(route('check-in.index'))->assertInertia(fn (Assert $page) => $page
            ->component('CheckIn/Index')
            ->has('people.data', 2)
            ->where('people.data.0.name', $zeroPerson->name)
            ->where('people.data.0.context', 'Zero Expected')
            ->where('people.data.0.check_in_status', 'complete')
            ->where('people.data.1.name', $person->name)
            ->where('people.data.1.type', 'artist')
            ->where('people.data.1.context', 'River Hollow')
            ->where('people.data.1.pass_name', 'Artist pass')
            ->where('people.data.1.issued', 1)
            ->where('people.data.1.expected', 1)
            ->where('people.data.1.check_in_status', 'complete')
            ->where('people.data.1.can_edit', true)
            ->where('people.meta.total', 2));
    }

    public function test_list_accepts_shared_filters_and_gates_edit_passes(): void
    {
        [$user, $event] = $this->context();
        [, $maya, $expected] = $this->heldEntitlement($event, 'River Hollow', 'Maya Chen');
        $this->heldEntitlement($event, 'Amber Field', 'Jordan Blake', 'Guest pass');

        Gate::define('manage-artists', fn (): bool => false);

        $this->actingAs($user)->get(route('check-in.index', [
            'type' => 'artist',
            'pass' => $expected->passAssignment->pass_type_id,
            'status' => 'not_started',
            'search' => 'maya',
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('CheckIn/Index')
            ->has('people.data', 1)
            ->where('people.data.0.name', $maya->name)
            ->where('people.data.0.can_edit', false)
            ->where('filters.type', 'artist')
            ->where('filters.status', 'not_started'));

        $this->get(route('check-in.index', ['type' => 'vendor']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 0)
                ->where('filters.type', 'vendor'));
    }

    public function test_nested_check_in_lists_redirect_to_the_shared_page(): void
    {
        [$user, $event] = $this->context();
        [$engagement] = $this->heldEntitlement($event, 'River Hollow');

        $this->actingAs($user)->get(route('artists.check-in'))->assertRedirect('/check-in');
        $this->get(route('vendors.check-in'))->assertRedirect('/check-in?type=vendor');
        $this->get(route('artists.check-in.show', $engagement))
            ->assertRedirect('/check-in/artists/'.$engagement->id);
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

        $this->actingAs($user)->post(route('check-in.issues.store', $expected), [
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
        $label = PassTypeLabel::query()->create(['name' => 'Wristband', 'color' => 'teal']);
        $expected->passAssignment->passType->labels()->attach($label);
        $unassigned = $engagement->passAssignments()->create([
            'pass_type_id' => $expected->passAssignment->pass_type_id,
        ]);
        $unassigned->expectedEntitlements()->create([
            'entitlement_item_id' => $expected->entitlement_item_id,
        ]);

        $this->actingAs($user)->get(route('check-in.show', [$engagement, 'person' => $maya->id]))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/CheckInShow')
            ->where('selectedPersonId', $maya->id)
            ->where('engagement.name', 'River Hollow')
            ->where('canWrite', true)
            ->where('event.timezone', 'America/Vancouver')
            ->has('engagement.people', 2)
            ->where('engagement.people.0.name', $maya->name)
            ->where('engagement.people.0.expected', 1)
            ->where('engagement.people.0.entitlements.0.status', 'pending')
            ->where('engagement.people.0.entitlements.0.locations.0.name', 'Main stage')
            ->where('engagement.people.0.pass_labels.0.name', 'Wristband')
            ->where('engagement.people.1.name', $riley->name)
            ->where('engagement.people.1.expected', 0));
    }

    public function test_show_rejects_wrong_event_and_unconfirmed_engagements(): void
    {
        [$user, $event] = $this->context();
        [$confirmed] = $this->heldEntitlement($event, 'River Hollow');
        $other = ArtistEngagement::factory()
            ->for($this->event('Other'))
            ->for(Artist::factory())
            ->create(['status' => 'confirmed']);
        $unconfirmed = ArtistEngagement::factory()
            ->for($event)
            ->for(Artist::factory())
            ->create(['status' => 'idea']);

        $this->actingAs($user)
            ->get(route('check-in.show', $other))
            ->assertNotFound();
        $this->get(route('check-in.show', $unconfirmed))
            ->assertNotFound();
        $this->get(route('check-in.show', $confirmed))
            ->assertOk();
    }

    public function test_consume_rejects_detached_person_wrong_event_unconfirmed_and_already_consumed(): void
    {
        [$user, $event] = $this->context();
        [$engagement, $person, $expected] = $this->heldEntitlement($event, 'River Hollow');
        $location = $event->locations()->create(['name' => 'Main stage']);
        $expected->entitlementItem->adjustments()->create([
            'location_id' => $location->id,
            'delta' => 3,
            'user_id' => $user->id,
        ]);

        $engagement->people()->detach($person);
        $this->actingAs($user)->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertNotFound();
        $engagement->people()->attach($person, ['is_primary' => true]);

        $otherEngagement = ArtistEngagement::factory()
            ->for($this->event('Other Fest'))
            ->for(Artist::factory())
            ->create(['status' => 'confirmed']);
        $otherPerson = Person::query()->create(['name' => 'Other', 'email' => 'other@example.com']);
        $otherEngagement->people()->attach($otherPerson, ['is_primary' => true]);
        $otherItem = $otherEngagement->event->entitlementItems()->create(['name' => 'Other band']);
        $otherPass = $otherEngagement->event->passTypes()->create(['name' => 'Other pass']);
        $otherAssignment = $otherEngagement->passAssignments()->create([
            'pass_type_id' => $otherPass->id,
            'person_id' => $otherPerson->id,
        ]);
        $otherExpected = $otherAssignment->expectedEntitlements()->create([
            'entitlement_item_id' => $otherItem->id,
            'status' => ExpectedEntitlement::STATUS_EXPECTED,
        ]);

        $this->post(route('check-in.issues.store', $otherExpected), [
            'location_id' => $location->id,
        ])->assertNotFound();

        $engagement->update(['status' => 'idea']);
        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertNotFound();
        $engagement->update(['status' => 'confirmed']);

        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertSessionHasErrors('expected_entitlement');
    }

    public function test_consume_rejects_invalid_stock_event_and_lock_states(): void
    {
        [$user, $event] = $this->context();
        [, , $expected] = $this->heldEntitlement($event, 'River Hollow');
        $location = $event->locations()->create(['name' => 'Empty']);

        $this->actingAs($user)->post(route('check-in.issues.store', $expected), [])
            ->assertSessionHasErrors('location_id');

        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertSessionHasErrors('quantity');

        $otherLocation = $this->event('Other')->locations()->create(['name' => 'Other']);
        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $otherLocation->id,
        ])->assertSessionHasErrors('location_id');

        $event->lock();
        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertForbidden();

        $this->get(route('check-in.show', ArtistEngagement::query()->first()))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Artists/CheckInShow')
                ->where('canWrite', false));
    }

    public function test_artist_permissions_protect_list_and_consume_routes(): void
    {
        [$user, $event] = $this->context();
        [$engagement, , $expected] = $this->heldEntitlement($event, 'River Hollow');
        $location = $event->locations()->create(['name' => 'Main stage']);

        Gate::define('view-artists', fn (): bool => false);
        $this->actingAs($user)->get(route('check-in.index'))->assertForbidden();
        $this->get(route('check-in.show', $engagement))->assertForbidden();

        Gate::define('manage-artists', fn (): bool => false);
        $this->post(route('check-in.issues.store', $expected), [
            'location_id' => $location->id,
        ])->assertForbidden();
    }

    public function test_list_paginates_people_and_scopes_search_in_the_query(): void
    {
        [$user, $event] = $this->context();
        $this->heldEntitlement($event, 'River Hollow', 'Maya Chen');
        for ($i = 1; $i <= 26; $i++) {
            $this->heldEntitlement($event, "Artist {$i}", sprintf('Person %02d', $i));
        }

        $this->actingAs($user)->get(route('check-in.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('CheckIn/Index')
                ->has('people.data', 25)
                ->where('people.meta.total', 27)
                ->where('people.meta.current_page', 1));

        $this->get(route('check-in.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 2)
                ->where('people.meta.current_page', 2));

        $this->get(route('check-in.index', ['search' => 'maya']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.name', 'Maya Chen')
                ->where('people.meta.total', 1));
    }

    public function test_legacy_artist_consume_post_redirects_to_shared_write_route(): void
    {
        [$user, $event] = $this->context();
        [, , $expected] = $this->heldEntitlement($event, 'River Hollow');

        $this->actingAs($user)
            ->post(route('artists.check-in.issues.store', $expected), [
                'location_id' => 1,
            ])
            ->assertRedirect(route('check-in.issues.store', $expected));
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
    private function heldEntitlement(
        Event $event,
        string $artistName,
        string $personName = 'Maya Chen',
        string $passName = 'Artist pass',
    ): array {
        $engagement = ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => $artistName]))->create(['status' => 'confirmed']);
        $person = Person::query()->create(['name' => $personName, 'email' => str($personName)->slug().'@example.com']);
        $engagement->people()->attach($person, ['is_primary' => true]);
        $item = $event->entitlementItems()->firstOrCreate(['name' => 'Artist wristband']);
        $pass = $event->passTypes()->firstOrCreate(['name' => $passName]);
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
