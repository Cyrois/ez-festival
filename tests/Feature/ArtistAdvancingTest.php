<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\ArtistType;
use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        $this->assertDatabaseCount('artists', 0);
    }

    public function test_list_uses_current_event_and_excludes_other_events(): void
    {
        [$user, $default] = $this->context();
        $current = $this->event('Current event');
        $user->setCurrentEvent($current);
        ArtistEngagement::factory()->for($default)->for(Artist::factory())->create();
        ArtistEngagement::factory()->create();
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $type = ArtistType::query()->create(['name' => 'Performance']);
        $label = ArtistLabel::factory()->create(['name' => 'Headliner', 'color' => 'warning']);
        $engagement = ArtistEngagement::factory()->for($current)->for($artist)->create([
            'status' => 'contract_sent',
            'artist_type_id' => $type->id,
        ]);
        $engagement->labels()->attach($label);

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Index')
            ->where('event.id', $current->id)
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
        [$user, $event] = $this->context();
        $vip = ArtistLabel::factory()->create(['name' => 'VIP']);
        $travel = ArtistLabel::factory()->create(['name' => 'Travel']);
        $match = Artist::factory()->create(['name' => 'River Hollow']);
        $partial = Artist::factory()->create(['name' => 'River Band']);
        $matchEngagement = ArtistEngagement::factory()->for($event)->for($match)->create();
        $partialEngagement = ArtistEngagement::factory()->for($event)->for($partial)->create();
        $matchEngagement->labels()->attach([$vip->id, $travel->id]);
        $partialEngagement->labels()->attach($vip);

        $this->actingAs($user)->get(route('artists.index', ['search' => 'river', 'labels' => [$vip->id, $travel->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.data.0.name', 'River Hollow')
                ->where('filters.labels', [$vip->id, $travel->id]));
    }

    public function test_search_treats_like_wildcards_as_literal_characters(): void
    {
        [$user, $event] = $this->context();
        $percent = Artist::factory()->create(['name' => '100% Real']);
        $underscore = Artist::factory()->create(['name' => 'Under_score']);
        ArtistEngagement::factory()->for($event)->for($percent)->create();
        ArtistEngagement::factory()->for($event)->for($underscore)->create();
        ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => '1000 Real']))->create();
        ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => 'UnderXscore']))->create();

        $this->actingAs($user)->get(route('artists.index', ['search' => '%']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.data.0.name', '100% Real'));

        $this->get(route('artists.index', ['search' => '_']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.data.0.name', 'Under_score'));
    }

    public function test_list_paginates_and_preserves_search(): void
    {
        [$user, $event] = $this->context();
        ArtistEngagement::factory()->count(26)->for($event)
            ->sequence(fn ($sequence) => [
                'artist_id' => Artist::factory()->create(['name' => 'Band '.$sequence->index])->id,
            ])->create();

        $this->actingAs($user)->get(route('artists.index', ['search' => 'Band', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('engagements.data', 1)
                ->where('engagements.meta.total', 26)
                ->where('engagements.meta.current_page', 2)
                ->where('engagements.links.prev', fn (string $url) => str_contains($url, 'search=Band')));
    }

    public function test_create_form_contains_organization_options_and_current_event(): void
    {
        [$user, $event] = $this->context();
        ArtistType::query()->create(['name' => 'Performance']);
        ArtistLabel::factory()->create(['name' => 'VIP']);

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
        [$user, $event] = $this->context();
        $type = ArtistType::query()->create(['name' => 'Performance']);
        $vip = ArtistLabel::factory()->create(['name' => 'VIP']);

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => '  River Hollow  ',
            'status' => 'outreach',
            'artist_type_id' => $type->id,
            'label_ids' => [$vip->id],
            'new_labels' => [['name' => 'Headliner', 'color' => 'rose']],
        ])->assertRedirect(route('artists.index'))->assertSessionHas('success', __('artists.toast.created'));

        $artist = Artist::query()->sole();
        $engagement = ArtistEngagement::query()->sole();
        $this->assertSame('River Hollow', $artist->name);
        $this->assertSame('river hollow', $artist->name_key);
        $this->assertDatabaseHas('artist_engagements', [
            'artist_id' => $artist->id,
            'event_id' => $event->id,
            'artist_type_id' => $type->id,
            'status' => 'outreach',
        ]);
        $this->assertSame(['Headliner', 'VIP'], $engagement->labels()->orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('artist_labels', [
            'name' => 'Headliner',
            'name_key' => 'headliner',
            'color' => 'rose',
        ]);
    }

    public function test_name_only_defaults_to_idea(): void
    {
        [$user, $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), ['name' => 'Maple & Pine'])
            ->assertRedirect(route('artists.index'));

        $this->assertDatabaseHas('artist_engagements', [
            'event_id' => $event->id,
            'status' => 'idea',
            'artist_type_id' => null,
        ]);
    }

    public function test_reuses_artist_case_insensitively_without_duplicating_row(): void
    {
        [$user, $event] = $this->context();
        Artist::factory()->create(['name' => 'River Hollow']);

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'river hollow',
        ])->assertRedirect(route('artists.index'));

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseHas('artists', ['name' => 'River Hollow', 'name_key' => 'river hollow']);
        $this->assertDatabaseCount('artist_engagements', 1);
    }

    public function test_returning_artist_preserves_history_and_labels_and_reuses_label_library(): void
    {
        [$user, $event] = $this->context();
        $artist = Artist::factory()->create(['name' => 'Maple & Pine']);
        $past = $this->event('Previous year');
        $history = ArtistEngagement::factory()->for($artist)->for($past)->create([
            'status' => 'confirmed',
        ]);
        $label = ArtistLabel::factory()->create(['name' => 'VIP', 'color' => 'soft_blue']);
        $history->labels()->attach($label);

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'Maple & Pine',
            'new_labels' => [['name' => 'VIP', 'color' => 'warning']],
        ])->assertRedirect(route('artists.index'));

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('artist_engagements', 2);
        $this->assertDatabaseCount('artist_labels', 1);
        $current = ArtistEngagement::query()->where('event_id', $event->id)->sole();
        $this->assertSame(['VIP'], $history->fresh()->labels()->pluck('name')->all());
        $this->assertSame(['VIP'], $current->labels()->pluck('name')->all());
        $this->assertSame('soft_blue', $label->fresh()->color);
        $this->assertSame('confirmed', $history->fresh()->status);
    }

    public function test_new_label_names_must_be_distinct_ignoring_case(): void
    {
        [$user, $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'River Hollow',
            'new_labels' => [
                ['name' => ' VIP ', 'color' => 'teal'],
                ['name' => 'vip', 'color' => 'warning'],
            ],
        ])->assertSessionHasErrors('new_labels.0.name');

        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('artist_labels', 0);
    }

    public function test_duplicate_engagement_is_rejected_without_creating_labels(): void
    {
        [$user, $event] = $this->context();
        ArtistEngagement::factory()->for($event)->for(Artist::factory()->state(['name' => 'River Hollow']))->create();

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'River Hollow',
            'new_labels' => [['name' => 'Unwanted label', 'color' => 'teal']],
        ])->assertSessionHasErrors(['name' => __('artists.errors.already_added')]);

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('artist_engagements', 1);
        $this->assertDatabaseCount('artist_labels', 0);
    }

    public function test_locked_event_remains_readable_but_blocks_form_and_writes(): void
    {
        [$user, $event] = $this->context();
        $event->lock();

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page->where('event.locked', true));
        $this->get(route('artists.create'))->assertForbidden();
        $this->post(route('artists.store', $event), ['name' => 'River Hollow'])->assertForbidden();
        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    public function test_unknown_organization_local_options_are_rejected(): void
    {
        [$user, $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), [
            'name' => 'River Hollow',
            'artist_type_id' => 999999,
            'label_ids' => [999999],
        ])->assertSessionHasErrors(['artist_type_id', 'label_ids.0']);
        $this->get(route('artists.index', ['labels' => [999999]]))->assertSessionHasErrors('labels.0');
        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    public function test_store_rejects_a_non_current_event(): void
    {
        [$user] = $this->context();
        $otherEvent = $this->event('Other event');

        $this->actingAs($user)->post(route('artists.store', $otherEvent), [
            'name' => 'River Hollow',
        ])->assertNotFound();

        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('artist_engagements', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_does_not_create_records(array $payload, string $field): void
    {
        [$user, $event] = $this->context();

        $this->actingAs($user)->post(route('artists.store', $event), $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('artists', 0);
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
            'empty label' => [['name' => 'Band', 'new_labels' => [['name' => '', 'color' => 'teal']]], 'new_labels.0.name'],
        ];
    }

    public function test_missing_effective_event_shows_an_empty_state(): void
    {
        [$user] = $this->context();
        $user->forceFill(['current_event_id' => null])->save();
        app(OrganizationContext::class)->organization()->forceFill(['active_event_id' => null])->save();

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('event', null)->has('engagements.data', 0));
        $this->get(route('artists.create'))->assertNotFound();
    }

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
