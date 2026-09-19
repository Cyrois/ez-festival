<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistEngagementNote;
use App\Models\ArtistLabel;
use App\Models\ArtistType;
use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArtistViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $engagement = ArtistEngagement::factory()->create();

        $this->get(route('artists.view', $engagement))->assertRedirect(route('login'));
        $this->put(route('artists.update', $engagement), ['name' => 'X', 'status' => 'idea'])->assertRedirect(route('login'));
        $this->post(route('artists.notes.store', $engagement), ['body' => 'Hello'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('artist_engagement_notes', 0);
    }

    public function test_can_open_view_for_engagement_on_effective_event(): void
    {
        [$user, $event] = $this->context();
        $type = ArtistType::query()->create(['name' => 'Performance']);
        $label = ArtistLabel::factory()->create(['name' => 'Headliner']);
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create([
            'status' => 'outreach',
            'artist_type_id' => $type->id,
        ]);
        $engagement->labels()->attach($label);
        ArtistEngagementNote::factory()->for($engagement, 'engagement')->for($user)->create([
            'body' => 'Older note',
            'created_at' => now()->subDay(),
        ]);
        ArtistEngagementNote::factory()->for($engagement, 'engagement')->for($user)->create([
            'body' => 'Newest note',
            'created_at' => now(),
        ]);

        $this->actingAs($user)->get(route('artists.view', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/View')
            ->where('engagement.name', 'River Hollow')
            ->where('engagement.status', 'outreach')
            ->where('engagement.artist_type_id', $type->id)
            ->where('engagement.labels.0.name', 'Headliner')
            ->where('event.id', $event->id)
            ->where('canWrite', true)
            ->has('notes', 2)
            ->where('notes.0.body', 'Newest note')
            ->where('notes.1.body', 'Older note')
            ->where('statuses', ArtistEngagement::STATUSES));
    }

    public function test_can_update_name_status_type_and_labels(): void
    {
        [$user, $event] = $this->context();
        $type = ArtistType::query()->create(['name' => 'Performance']);
        $vip = ArtistLabel::factory()->create(['name' => 'VIP']);
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create(['status' => 'idea']);

        $this->actingAs($user)->put(route('artists.update', $engagement), [
            'name' => '  River Hollow Trio  ',
            'status' => 'negotiating',
            'artist_type_id' => $type->id,
            'label_ids' => [$vip->id],
            'new_labels' => [['name' => 'Travel', 'color' => 'warning']],
        ])->assertRedirect(route('artists.view', $engagement))
            ->assertSessionHas('success', __('artists.toast.updated'));

        $artist->refresh();
        $engagement->refresh();
        $this->assertSame('River Hollow Trio', $artist->name);
        $this->assertSame('negotiating', $engagement->status);
        $this->assertSame($type->id, $engagement->artist_type_id);
        $this->assertSame(['Travel', 'VIP'], $engagement->labels()->orderBy('name')->pluck('name')->all());
    }

    public function test_locked_event_view_is_readable_but_blocks_update_and_note_post(): void
    {
        [$user, $event] = $this->context();
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create(['status' => 'idea']);
        $event->lock();

        $this->actingAs($user)->get(route('artists.view', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/View')
            ->where('canWrite', false)
            ->where('engagement.name', 'River Hollow'));

        $this->put(route('artists.update', $engagement), [
            'name' => 'Changed',
            'status' => 'confirmed',
        ])->assertForbidden();

        $this->post(route('artists.notes.store', $engagement), [
            'body' => 'Should not post',
        ])->assertForbidden();

        $this->assertSame('River Hollow', $artist->fresh()->name);
        $this->assertSame('idea', $engagement->fresh()->status);
        $this->assertDatabaseCount('artist_engagement_notes', 0);
    }

    public function test_can_post_note_newest_first_and_cannot_when_locked(): void
    {
        [$user, $event] = $this->context();
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create();

        $this->actingAs($user)->post(route('artists.notes.store', $engagement), [
            'body' => '  First outreach sent.  ',
        ])->assertRedirect(route('artists.view', $engagement))
            ->assertSessionHas('success', __('artists.toast.note_posted'));

        $this->assertDatabaseHas('artist_engagement_notes', [
            'artist_engagement_id' => $engagement->id,
            'user_id' => $user->id,
            'body' => 'First outreach sent.',
        ]);

        $this->post(route('artists.notes.store', $engagement), [
            'body' => 'Agent replied.',
        ])->assertRedirect(route('artists.view', $engagement));

        $this->get(route('artists.view', $engagement))->assertInertia(fn (Assert $page) => $page
            ->has('notes', 2)
            ->where('notes.0.body', 'Agent replied.')
            ->where('notes.1.body', 'First outreach sent.')
            ->where('notes.0.author', $user->name));

        $event->lock();
        $this->post(route('artists.notes.store', $engagement), ['body' => 'Blocked'])->assertForbidden();
        $this->assertDatabaseCount('artist_engagement_notes', 2);
    }

    public function test_wrong_event_engagements_are_not_found(): void
    {
        [$user, $event] = $this->context();
        $otherEvent = $this->event('Other event');
        $wrongEvent = ArtistEngagement::factory()
            ->for($otherEvent)
            ->for(Artist::factory()->create(['name' => 'Local Act']))
            ->create();

        $this->actingAs($user)->get(route('artists.view', $wrongEvent))->assertNotFound();
        $this->put(route('artists.update', $wrongEvent), ['name' => 'X', 'status' => 'idea'])->assertNotFound();
        $this->post(route('artists.notes.store', $wrongEvent), ['body' => 'Nope'])->assertNotFound();

        $this->assertDatabaseCount('artist_engagement_notes', 0);
        $this->assertSame($event->id, $user->effectiveEvent()->id);
    }

    public function test_index_links_include_engagement_ids_for_view(): void
    {
        [$user, $event] = $this->context();
        $artist = Artist::factory()->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create();

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('engagements.data.0.id', $engagement->id)
            ->where('engagements.data.0.name', 'River Hollow'));
    }

    public function test_blank_note_body_is_rejected(): void
    {
        [$user, $event] = $this->context();
        $engagement = ArtistEngagement::factory()
            ->for($event)
            ->for(Artist::factory())
            ->create();

        $this->actingAs($user)->post(route('artists.notes.store', $engagement), [
            'body' => '   ',
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('artist_engagement_notes', 0);
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
