<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\TeamForm;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\ReturnNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReturnNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        $this->user = $this->grantAdminAccess(User::factory()->create());
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_direct_pages_have_named_fallbacks_and_landing_pages_supply_their_current_query(): void
    {
        foreach (['artists.create' => '/artists/advancing', 'vendors.create' => '/vendors/advancing', 'team.members.create' => '/team/advancement', 'credentials.passes.create' => '/credentials/passes', 'settings.events.create' => '/settings/events'] as $route => $href) {
            $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->where('returnNavigation.href', $href));
        }
        $this->get('/check-in?type=team&search=Maya')->assertInertia(fn (Assert $page) => $page
            ->where('returnNavigation.href', '/check-in?type=team&search=Maya')->where('returnNavigation.landing', true));
    }

    public function test_detail_origin_survives_a_save_and_note_post_without_entering_the_domain_payload(): void
    {
        $artist = ArtistEngagement::factory()->for($this->event)->create(['status' => 'confirmed']);
        $origin = '/check-in?type=artist&status=complete&search=River&restore=1';
        $url = route('artists.view', ['engagement' => $artist, 'return_to' => $origin]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('returnNavigation', ['href' => $origin, 'page' => 'check_in.title']));
        $this->put(route('artists.update', ['engagement' => $artist, 'return_to' => $origin]), ['name' => 'River', 'status' => 'confirmed'])
            ->assertRedirect($url);
        $this->post(route('artists.notes.store', ['engagement' => $artist, 'return_to' => $origin]), ['body' => 'A note'])
            ->assertRedirect($url);
        $this->assertSame('River', $artist->fresh()->artist->name);
        $this->assertDatabaseHas('artist_engagement_notes', ['artist_engagement_id' => $artist->id, 'body' => 'A note']);
    }

    public function test_team_member_returns_to_the_originating_schedule_and_direct_shifts_keep_the_existing_context(): void
    {
        $member = TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => Person::create(['name' => 'Maya', 'email' => 'maya@example.test'])->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $origin = '/team/scheduling?tab=schedule&date=2026-10-02&view=location_shifts&restore=1';
        $this->get(route('team.members.show', ['engagement' => $member, 'return_to' => $origin]))
            ->assertInertia(fn (Assert $page) => $page->where('returnNavigation.href', $origin)->where('returnNavigation.page', 'nav.team.scheduling'));
        $this->get('/team/shifts/create?return_tab=schedule&schedule_date=2026-10-02&schedule_view=all_locations')
            ->assertInertia(fn (Assert $page) => $page->where('returnNavigation.href', '/team/scheduling?tab=schedule&date=2026-10-02&view=all_locations'));
    }

    public function test_list_redirects_restore_the_same_landing_state_and_preview_is_authenticated_only(): void
    {
        $origin = '/settings/events?restore=1';
        $this->post(route('settings.events.store', ['return_to' => $origin]), ['name' => 'Next festival', 'starts_on' => '2027-10-01', 'ends_on' => '2027-10-03', 'timezone' => 'America/Vancouver'])
            ->assertRedirect(url($origin));
        $form = TeamForm::create(['event_id' => $this->event->id, 'name' => 'Applications', 'slug' => 'applications', 'status' => 'live']);
        $this->get(route('team.forms.preview', $form))->assertInertia(fn (Assert $page) => $page->where('preview', true)->where('returnNavigation.href', '/team/forms'));
        $this->get(route('team.forms.public.show', $form->slug))->assertInertia(fn (Assert $page) => $page->where('preview', false)->where('returnNavigation', null));
    }

    public function test_legacy_check_in_retains_person_and_return_context(): void
    {
        $artist = ArtistEngagement::factory()->for($this->event)->create();
        $origin = '/check-in?type=artist&restore=1';
        $person = Person::create(['name' => 'Contact', 'email' => 'contact@example.test']);
        $this->get(route('artists.check-in.show', ['engagement' => $artist, 'person' => $person->id, 'return_to' => $origin]))
            ->assertRedirect(route('check-in.show', ['engagement' => $artist, 'person' => $person->id, 'return_to' => $origin]));
    }

    public function test_unsafe_context_is_rejected_by_the_form_request_before_a_write(): void
    {
        foreach (['https://example.test/check-in', '//example.test/check-in', '/check-in#x', '/check-in?return_to=/settings/team', '/check-in?event_id=3', '/check-in?search[x][y]=value', '/settings/team/1', ['/check-in']] as $origin) {
            $this->postJson(route('artists.store', ['event' => $this->event, 'return_to' => $origin]), ['name' => 'Do not create', 'status' => 'idea'])
                ->assertUnprocessable()->assertJsonValidationErrors('return_to');
        }
        $this->assertDatabaseCount('artist_engagements', 0);
        $this->assertFalse(ReturnNavigation::valid('/check-in\\evil'));
    }

    public function test_context_does_not_grant_permissions_or_bypass_event_and_lock_gates(): void
    {
        $artist = ArtistEngagement::factory()->for($this->event)->create(['status' => 'confirmed']);
        $foreign = ArtistEngagement::factory()->create();
        $reader = $this->grantRoleAccess(User::factory()->create(), ['artists.view']);
        $reader->setCurrentEvent($this->event);
        $this->actingAs($reader)->get(route('artists.view', ['engagement' => $artist, 'return_to' => '/settings/team']))
            ->assertInertia(fn (Assert $page) => $page->where('returnNavigation.href', '/artists/advancing'));
        $this->get(route('artists.view', ['engagement' => $foreign, 'return_to' => '/artists/advancing']))->assertNotFound();
        $this->actingAs($this->user);
        $this->event->lock();
        $this->actingAs($this->user->fresh());
        $this->get(route('artists.view', ['engagement' => $artist, 'return_to' => '/check-in']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canWrite', false)->where('returnNavigation.href', '/check-in'));
        $this->put(route('artists.update', ['engagement' => $artist, 'return_to' => '/check-in']), ['name' => 'Blocked', 'status' => 'confirmed'])->assertForbidden();
    }
}
