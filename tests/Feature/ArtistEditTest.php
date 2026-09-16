<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistEngagementNote;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArtistEditTest extends TestCase
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

        $this->get(route('artists.edit', $engagement))->assertRedirect(route('login'));
        $this->put(route('artists.update', $engagement), ['name' => 'X', 'status' => 'idea'])->assertRedirect(route('login'));
        $this->post(route('artists.notes.store', $engagement), ['body' => 'Hello'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('artist_engagement_notes', 0);
    }

    public function test_can_open_edit_for_engagement_on_effective_event(): void
    {
        [$user, $organization, $event] = $this->context();
        $type = $organization->artistTypes()->create(['name' => 'Performance']);
        $label = ArtistLabel::factory()->for($organization)->create(['name' => 'Headliner']);
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $artist->labels()->attach($label);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create([
            'status' => 'outreach',
            'artist_type_id' => $type->id,
        ]);
        ArtistEngagementNote::factory()->for($engagement, 'engagement')->for($user)->create([
            'body' => 'Older note',
            'created_at' => now()->subDay(),
        ]);
        ArtistEngagementNote::factory()->for($engagement, 'engagement')->for($user)->create([
            'body' => 'Newest note',
            'created_at' => now(),
        ]);

        $this->actingAs($user)->get(route('artists.edit', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Edit')
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
        [$user, $organization, $event] = $this->context();
        $type = $organization->artistTypes()->create(['name' => 'Performance']);
        $vip = ArtistLabel::factory()->for($organization)->create(['name' => 'VIP']);
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create(['status' => 'idea']);

        $this->actingAs($user)->put(route('artists.update', $engagement), [
            'name' => '  River Hollow Trio  ',
            'status' => 'negotiating',
            'artist_type_id' => $type->id,
            'label_ids' => [$vip->id],
            'new_labels' => [['name' => 'Travel', 'color' => 'warning']],
            'notes' => 'Must not write legacy column',
        ])->assertRedirect(route('artists.edit', $engagement))
            ->assertSessionHas('success', __('artists.toast.updated'));

        $artist->refresh();
        $engagement->refresh();
        $this->assertSame('River Hollow Trio', $artist->name);
        $this->assertSame('negotiating', $engagement->status);
        $this->assertSame($type->id, $engagement->artist_type_id);
        $this->assertNull($engagement->notes);
        $this->assertSame(['Travel', 'VIP'], $artist->labels()->orderBy('name')->pluck('name')->all());
    }

    public function test_locked_event_edit_is_readable_but_blocks_update_and_note_post(): void
    {
        [$user, $organization, $event] = $this->context();
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create(['status' => 'idea']);
        $event->lock();

        $this->actingAs($user)->get(route('artists.edit', $engagement))->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Edit')
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
        [$user, $organization, $event] = $this->context();
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create();

        $this->actingAs($user)->post(route('artists.notes.store', $engagement), [
            'body' => '  First outreach sent.  ',
        ])->assertRedirect(route('artists.edit', $engagement))
            ->assertSessionHas('success', __('artists.toast.note_posted'));

        $this->assertDatabaseHas('artist_engagement_notes', [
            'artist_engagement_id' => $engagement->id,
            'user_id' => $user->id,
            'body' => 'First outreach sent.',
        ]);

        $this->post(route('artists.notes.store', $engagement), [
            'body' => 'Agent replied.',
        ])->assertRedirect(route('artists.edit', $engagement));

        $this->get(route('artists.edit', $engagement))->assertInertia(fn (Assert $page) => $page
            ->has('notes', 2)
            ->where('notes.0.body', 'Agent replied.')
            ->where('notes.1.body', 'First outreach sent.')
            ->where('notes.0.author', $user->name));

        $event->lock();
        $this->post(route('artists.notes.store', $engagement), ['body' => 'Blocked'])->assertForbidden();
        $this->assertDatabaseCount('artist_engagement_notes', 2);
    }

    public function test_cross_org_and_wrong_event_engagements_are_not_found(): void
    {
        [$user, $organization, $event] = $this->context();
        [, $other, $foreignEvent] = $this->context();

        $foreign = ArtistEngagement::factory()
            ->for($foreignEvent)
            ->for(Artist::factory()->for($other)->create())
            ->create();

        $otherEvent = $this->event($organization, 'Other event');
        $wrongEvent = ArtistEngagement::factory()
            ->for($otherEvent)
            ->for(Artist::factory()->for($organization)->create(['name' => 'Local Act']))
            ->create();

        $this->actingAs($user)->get(route('artists.edit', $foreign))->assertNotFound();
        $this->put(route('artists.update', $foreign), ['name' => 'X', 'status' => 'idea'])->assertNotFound();
        $this->post(route('artists.notes.store', $foreign), ['body' => 'Nope'])->assertNotFound();

        $this->get(route('artists.edit', $wrongEvent))->assertNotFound();
        $this->put(route('artists.update', $wrongEvent), ['name' => 'X', 'status' => 'idea'])->assertNotFound();
        $this->post(route('artists.notes.store', $wrongEvent), ['body' => 'Nope'])->assertNotFound();

        $this->assertDatabaseCount('artist_engagement_notes', 0);
        $this->assertSame($event->id, $user->effectiveEvent($organization)->id);
    }

    public function test_index_links_include_engagement_ids_for_edit(): void
    {
        [$user, $organization, $event] = $this->context();
        $artist = Artist::factory()->for($organization)->create(['name' => 'River Hollow']);
        $engagement = ArtistEngagement::factory()->for($event)->for($artist)->create();

        $this->actingAs($user)->get(route('artists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('engagements.data.0.id', $engagement->id)
            ->where('engagements.data.0.name', 'River Hollow'));
    }

    public function test_blank_note_body_is_rejected(): void
    {
        [$user, $organization, $event] = $this->context();
        $engagement = ArtistEngagement::factory()
            ->for($event)
            ->for(Artist::factory()->for($organization))
            ->create();

        $this->actingAs($user)->post(route('artists.notes.store', $engagement), [
            'body' => '   ',
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('artist_engagement_notes', 0);
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
