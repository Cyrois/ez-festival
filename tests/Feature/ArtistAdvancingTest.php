<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArtistAdvancingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('artists.index'))->assertRedirect(route('login'));
        $this->get(route('artists.create'))->assertRedirect(route('login'));
        $this->post('/events/1/artists', ['name' => 'River Hollow'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('organization_artists', 0);
    }

    public function test_permission_controls_navigation_and_all_artist_actions(): void
    {
        [$user, $organization, $event] = $this->context();
        $user->organizations()->updateExistingPivot($organization->id, ['can_manage_artists' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('artists.manage'));
        $this->actingAs($user)->get(route('artists.index'))->assertForbidden();
        $this->get(route('artists.create'))->assertForbidden();
        $this->post(route('artists.store', $event), ['name' => 'River Hollow'])->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can.manage_artists', false));
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    public function test_organization_creator_gets_permission_but_new_members_do_not(): void
    {
        $creator = User::factory()->create();
        $organization = $creator->ensureOrganization();
        $member = User::factory()->create();
        $member->organizations()->attach($organization);

        $this->assertTrue(Gate::forUser($creator)->allows('artists.manage'));
        $this->assertFalse(Gate::forUser($member)->allows('artists.manage'));
    }

    public function test_list_uses_membership_event_and_excludes_other_events_and_organizations(): void
    {
        [$user, $organization, $primary] = $this->context();
        $current = $this->event($organization, 'Current event');
        $user->setCurrentEvent($organization, $current);
        ArtistEngagement::factory()->for($primary)->for(Artist::factory()->for($organization))->create();
        ArtistEngagement::factory()->create();
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $type = $organization->artistTypes()->create(['name' => 'Performance']);
        $label = ArtistLabel::factory()->for($organization)->create(['name' => 'Headliner', 'color' => 'warning']);
        $artist->labels()->attach($label);
        ArtistEngagement::factory()->for($current)->for($artist)->create(['status' => 'contract_sent', 'artist_type_id' => $type->id]);

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Index')
            ->where('event.id', $current->id)
            ->where('auth.can.manage_artists', true)
            ->has('engagements.data', 1)
            ->where('engagements.data.0.name', 'River Hollow')
            ->where('engagements.data.0.status', 'contract_sent')
            ->where('engagements.data.0.type', 'Performance')
            ->where('engagements.data.0.labels.0.name', 'Headliner')
            ->where('engagements.data.0.custom', [])
            ->missing('engagements.data.0.fee'));
    }

    public function test_search_and_all_selected_labels_filter_the_list(): void
    {
        [$user, $organization, $event] = $this->context();
        $vip = ArtistLabel::factory()->for($organization)->create(['name' => 'VIP']);
        $travel = ArtistLabel::factory()->for($organization)->create(['name' => 'Travel']);
        $match = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $match->labels()->attach([$vip->id, $travel->id]);
        $partial = Artist::factory()->for($organization)->create(['name' => 'River Band']);
        $partial->labels()->attach($vip);
        ArtistEngagement::factory()->for($event)->for($match)->create();
        ArtistEngagement::factory()->for($event)->for($partial)->create();

        $this->actingAs($user)->get(route('artists.index', ['search' => 'river', 'labels' => [$vip->id, $travel->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.data.0.name', 'River Hollow')
                ->where('filters.labels', [$vip->id, $travel->id]));
    }

    public function test_list_paginates_and_preserves_search(): void
    {
        [$user, $organization, $event] = $this->context();
        ArtistEngagement::factory()->count(26)->for($event)
            ->sequence(fn ($sequence) => [
                'artist_id' => Artist::factory()->for($organization)->create(['name' => 'Band '.$sequence->index])->id,
            ])->create();

        $this->actingAs($user)->get(route('artists.index', ['search' => 'Band', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.meta.total', 26)
                ->where('engagements.meta.current_page', 2)
                ->where('engagements.links.prev', fn (string $url) => str_contains($url, 'search=Band')));
    }

    public function test_create_form_contains_only_organization_options_and_current_event(): void
    {
        [$user, $organization, $event] = $this->context();
        $organization->artistTypes()->create(['name' => 'Performance']);
        ArtistLabel::factory()->for($organization)->create(['name' => 'VIP']);
        ArtistLabel::factory()->create(['name' => 'Private label']);

        $this->actingAs($user)->get(route('artists.create'))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Create')
            ->where('event.id', $event->id)
            ->has('types', 1)
            ->has('labels', 1)
            ->where('labels.0.name', 'VIP')
            ->where('statuses', ['idea', 'outreach', 'negotiating', 'contract_sent', 'confirmed', 'declined']));
    }

    public function test_saves_artist_type_status_and_existing_and_new_labels(): void
    {
        [$user, $organization, $event] = $this->context();
        $type = $organization->artistTypes()->create(['name' => 'Performance']);
        $vip = ArtistLabel::factory()->for($organization)->create(['name' => 'VIP']);

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => '  River Hollow  ',
            'status' => 'outreach',
            'artist_type_id' => $type->id,
            'label_ids' => [$vip->id],
            'new_labels' => [['name' => 'Headliner', 'color' => 'warning']],
            'organization_id' => 9999,
            'notes' => 'Not a writable field',
        ])->assertRedirect(route('artists.index'))->assertSessionHas('success', __('artists.toast.created'));

        $artist = Artist::query()->sole();
        $this->assertSame('River Hollow', $artist->name);
        $this->assertSame($organization->id, $artist->organization_id);
        $this->assertDatabaseHas('artist_engagements', ['artist_id' => $artist->id, 'event_id' => $event->id, 'artist_type_id' => $type->id, 'status' => 'outreach', 'notes' => null]);
        $this->assertSame(['Headliner', 'VIP'], $artist->labels()->orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('artist_labels', ['organization_id' => $organization->id, 'name' => 'Headliner', 'color' => 'warning']);
    }

    public function test_name_only_defaults_to_idea(): void
    {
        [$user, , $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), ['name' => 'Maple & Pine'])
            ->assertRedirect(route('artists.index'));

        $this->assertDatabaseHas('artist_engagements', ['event_id' => $event->id, 'status' => 'idea', 'artist_type_id' => null]);
    }

    public function test_returning_artist_preserves_history_and_labels_and_reuses_label_library(): void
    {
        [$user, $organization, $event] = $this->context();
        $artist = Artist::factory()->for($organization)->create(['name' => 'Maple & Pine']);
        $past = $this->event($organization, 'Previous year');
        $history = ArtistEngagement::factory()->for($artist)->for($past)->create(['status' => 'confirmed', 'notes' => 'Past notes']);
        $label = ArtistLabel::factory()->for($organization)->create(['name' => 'VIP', 'color' => 'secondary']);
        $artist->labels()->attach($label);

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'Maple & Pine',
            'new_labels' => [['name' => 'VIP', 'color' => 'warning']],
        ])->assertRedirect(route('artists.index'));

        $this->assertDatabaseCount('organization_artists', 1);
        $this->assertDatabaseCount('artist_engagements', 2);
        $this->assertDatabaseCount('artist_labels', 1);
        $this->assertDatabaseCount('artist_label_assignments', 1);
        $this->assertSame('secondary', $label->fresh()->color);
        $this->assertSame('confirmed', $history->fresh()->status);
        $this->assertSame('Past notes', $history->fresh()->notes);
    }

    public function test_duplicate_engagement_is_rejected_without_creating_labels(): void
    {
        [$user, $organization, $event] = $this->context();
        ArtistEngagement::factory()->for($event)->for(Artist::factory()->for($organization)->state(['name' => 'River Hollow']))->create();

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'River Hollow',
            'new_labels' => [['name' => 'Unwanted label', 'color' => 'primary']],
        ])->assertSessionHasErrors(['name' => __('artists.errors.already_added')]);

        $this->assertDatabaseCount('organization_artists', 1);
        $this->assertDatabaseCount('artist_engagements', 1);
        $this->assertDatabaseCount('artist_labels', 0);
    }

    public function test_locked_event_remains_readable_but_blocks_form_and_writes(): void
    {
        [$user, , $event] = $this->context();
        $event->lock();

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page->where('event.locked', true));
        $this->get(route('artists.create'))->assertForbidden();
        $this->post(route('artists.store', $event), ['name' => 'River Hollow'])->assertForbidden();
        $this->assertDatabaseCount('organization_artists', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    public function test_cross_organization_event_is_not_found_and_foreign_options_are_rejected(): void
    {
        [$user, , $event] = $this->context();
        [, $other, $foreignEvent] = $this->context();
        $type = $other->artistTypes()->create(['name' => 'Private']);
        $label = ArtistLabel::factory()->for($other)->create();

        $this->actingAs($user)->post(route('artists.store', $foreignEvent), ['name' => 'Intruder'])->assertNotFound();
        $this->post(route('artists.store', $event), ['name' => 'River Hollow', 'artist_type_id' => $type->id, 'label_ids' => [$label->id]])
            ->assertSessionHasErrors(['artist_type_id', 'label_ids.0']);
        $this->get(route('artists.index', ['labels' => [$label->id]]))->assertSessionHasErrors('labels.0');
        $this->assertDatabaseCount('organization_artists', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_does_not_create_records(array $payload, string $field): void
    {
        [$user, , $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('organization_artists', 0);
        $this->assertDatabaseCount('artist_labels', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [[], 'name'],
            'blank name' => [['name' => '   '], 'name'],
            'long name' => [['name' => str_repeat('a', 256)], 'name'],
            'obsolete status' => [['name' => 'Band', 'status' => 'contract_signed'], 'status'],
            'invalid color' => [['name' => 'Band', 'new_labels' => [['name' => 'VIP', 'color' => 'url(unsafe)']]], 'new_labels.0.color'],
            'empty label' => [['name' => 'Band', 'new_labels' => [['name' => '', 'color' => 'primary']]], 'new_labels.0.name'],
        ];
    }

    public function test_missing_effective_event_shows_an_empty_state(): void
    {
        [$user, $organization] = $this->context();
        $organization->update(['active_event_id' => null]);

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('event', null)->has('engagements.data', 0));
        $this->get(route('artists.create'))->assertNotFound();
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = $user->ensureOrganization();
        $event = $this->event($organization, 'Festival');
        $organization->update(['active_event_id' => $event->id, 'setup_completed_at' => now()]);

        return [$user, $organization, $event];
    }

    private function event(Organization $organization, string $name): Event
    {
        return $organization->events()->create([
            'name' => $name,
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }
}
