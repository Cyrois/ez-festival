<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\TeamEngagementNote;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamMemberNotesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_active_role_permission_controls_page_data_and_saving_per_event(): void
    {
        $eventA = $this->event('Event A');
        $eventB = $this->event('Event B');
        $allowed = Role::query()->create([
            'name' => 'Notes reader',
            'can_read_team_notes' => true,
        ]);
        $plain = Role::query()->create(['name' => 'Plain role']);
        $user = $this->nonAdminFor($eventA, $allowed);
        $this->selfEngagement($user, $eventB, $plain);
        $targetA = $this->engagement($eventA, 'Target A');
        $targetB = $this->engagement($eventB, 'Target B');
        TeamEngagementNote::query()->create([
            'team_engagement_id' => $targetA->id,
            'user_id' => $user->id,
            'body' => 'Visible note',
        ]);
        TeamEngagementNote::query()->create([
            'team_engagement_id' => $targetB->id,
            'user_id' => $user->id,
            'body' => 'Hidden note',
        ]);

        $this->actingAs($user)->get(route('team.members.show', $targetA))->assertInertia(
            fn (Assert $page) => $page
                ->where('canReadNotes', true)
                ->has('notes', 1)
                ->where('notes.0.body', 'Visible note'),
        );

        $this->put(route('team.members.update', $targetA), $this->payload($targetA, [
            'notes' => [
                ['body' => '  Newest saved with page  '],
                ['body' => 'Older saved with page'],
            ],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('team_engagement_notes', [
            'team_engagement_id' => $targetA->id,
            'user_id' => $user->id,
            'body' => 'Newest saved with page',
        ]);
        $this->get(route('team.members.show', $targetA))->assertInertia(
            fn (Assert $page) => $page
                ->where('notes.0.body', 'Newest saved with page')
                ->where('notes.1.body', 'Older saved with page'),
        );

        $user->setCurrentEvent($eventB);
        $this->get(route('team.members.show', $targetB))->assertInertia(
            fn (Assert $page) => $page
                ->where('canReadNotes', false)
                ->missing('notes'),
        );
        $this->put(route('team.members.update', $targetB), $this->payload($targetB, [
            'notes' => [['body' => 'Not allowed']],
        ]))->assertSessionHasErrors('notes');
        $this->assertDatabaseMissing('team_engagement_notes', ['body' => 'Not allowed']);
    }

    public function test_permission_removal_and_an_off_role_remove_note_access_but_renaming_does_not(): void
    {
        $event = $this->event();
        $role = Role::query()->create([
            'name' => 'Original name',
            'can_read_team_notes' => true,
        ]);
        $user = $this->nonAdminFor($event, $role);
        $target = $this->engagement($event, 'Target');

        $role->update(['name' => 'Renamed role']);
        $this->actingAs($user)->get(route('team.members.show', $target))->assertInertia(
            fn (Assert $page) => $page->where('canReadNotes', true),
        );

        $role->update(['can_read_team_notes' => false]);
        $this->get(route('team.members.show', $target))->assertInertia(
            fn (Assert $page) => $page->where('canReadNotes', false)->missing('notes'),
        );

        $role->update(['can_read_team_notes' => true, 'active' => false]);
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'notes' => [['body' => 'Blocked']],
        ]))->assertRedirect();
        $this->assertDatabaseMissing('team_engagement_notes', ['body' => 'Blocked']);
    }

    public function test_admin_reads_and_adds_notes_without_an_event_role(): void
    {
        $event = $this->event();
        $admin = User::factory()->create();
        $this->finishSetup($admin, $event);
        $this->grantAdminAccess($admin);
        $target = $this->engagement($event, 'Admin target');

        $this->actingAs($admin)->get(route('team.members.show', $target))->assertInertia(
            fn (Assert $page) => $page->where('canReadNotes', true)->has('notes', 0),
        );
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'notes' => [['body' => 'Admin note']],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('team_engagement_notes', [
            'team_engagement_id' => $target->id,
            'user_id' => $admin->id,
            'body' => 'Admin note',
        ]);
    }

    public function test_note_validation_rejects_blank_too_long_and_locked_event_notes(): void
    {
        $event = $this->event();
        $admin = User::factory()->create();
        $this->finishSetup($admin, $event);
        $this->grantAdminAccess($admin);
        $target = $this->engagement($event, 'Validation target');

        $this->actingAs($admin)->put(route('team.members.update', $target), $this->payload($target, [
            'notes' => [['body' => '   ']],
        ]))->assertSessionHasErrors('notes.0.body');
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'notes' => [['body' => str_repeat('x', 5001)]],
        ]))->assertSessionHasErrors('notes.0.body');

        $event->lock();
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'notes' => [['body' => 'Locked']],
        ]))->assertForbidden();
        $this->assertDatabaseCount('team_engagement_notes', 0);
    }

    public function test_author_can_edit_repeatedly_only_during_original_five_minute_window(): void
    {
        Carbon::setTestNow('2026-09-30 12:00:00');
        $event = $this->event();
        $role = Role::query()->create([
            'name' => 'Notes reader',
            'can_read_team_notes' => true,
        ]);
        $author = $this->nonAdminFor($event, $role);
        $target = $this->engagement($event, 'Edit target');
        $note = TeamEngagementNote::query()->create([
            'team_engagement_id' => $target->id,
            'user_id' => $author->id,
            'body' => 'Original',
        ]);
        $originalTime = $note->created_at->toIso8601String();

        Carbon::setTestNow('2026-09-30 12:04:00');
        $this->actingAs($author)->put(route('team.members.update', $target), $this->payload($target, [
            'note_edits' => [['id' => $note->id, 'body' => 'First edit']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('First edit', $note->fresh()->body);
        $this->assertNotNull($note->fresh()->edited_at);
        $this->assertSame($originalTime, $note->fresh()->created_at->toIso8601String());
        $this->get(route('team.members.show', $target))->assertInertia(
            fn (Assert $page) => $page
                ->where('notes.0.body', 'First edit')
                ->where('notes.0.edited_at', fn ($value) => $value !== null)
                ->where(
                    'notes.0.editable_until',
                    fn ($value) => Carbon::parse($value)->equalTo(Carbon::parse('2026-09-30 12:05:00')),
                ),
        );

        Carbon::setTestNow('2026-09-30 12:04:30');
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'note_edits' => [['id' => $note->id, 'body' => 'Second edit']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Second edit', $note->fresh()->body);

        Carbon::setTestNow('2026-09-30 12:05:00');
        $this->put(route('team.members.update', $target), $this->payload($target, [
            'status' => 'hired',
            'note_edits' => [['id' => $note->id, 'body' => 'Too late']],
        ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', __('team.member.notes.errors.edit_window_closed'));
        $this->assertSame('hired', $target->fresh()->status);
        $this->assertSame('Second edit', $note->fresh()->body);
    }

    public function test_another_person_cannot_edit_a_note_and_notes_have_no_delete_route(): void
    {
        $event = $this->event();
        $role = Role::query()->create([
            'name' => 'Notes reader',
            'can_read_team_notes' => true,
        ]);
        $author = $this->nonAdminFor($event, $role);
        $other = User::factory()->create();
        $other->person()->update(['can_log_in' => true]);
        $this->selfEngagement($other, $event, $role);
        $other->setCurrentEvent($event);
        $target = $this->engagement($event, 'Protected note target');
        $note = TeamEngagementNote::query()->create([
            'team_engagement_id' => $target->id,
            'user_id' => $author->id,
            'body' => 'Author text',
        ]);

        $this->actingAs($other)->put(route('team.members.update', $target), $this->payload($target, [
            'note_edits' => [['id' => $note->id, 'body' => 'Other text']],
        ]))->assertSessionHasErrors('note_edits.0.id');
        $this->assertSame('Author text', $note->fresh()->body);

        $this->delete("/team/members/{$target->id}/notes/{$note->id}")
            ->assertNotFound();
    }

    private function event(string $name = 'Festival'): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }

    private function nonAdminFor(Event $event, Role $role): User
    {
        $user = User::factory()->create();
        $user->person()->update(['can_log_in' => true]);
        $this->finishSetup($user, $event);
        $this->selfEngagement($user, $event, $role);

        return $user->fresh();
    }

    private function finishSetup(User $user, Event $event): void
    {
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);
    }

    private function selfEngagement(User $user, Event $event, Role $role): TeamEngagement
    {
        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
    }

    private function engagement(Event $event, string $name): TeamEngagement
    {
        $person = Person::query()->create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
        ]);

        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'status' => 'applied',
            'employment_type' => 'volunteer',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(TeamEngagement $engagement, array $overrides = []): array
    {
        $engagement->loadMissing('person');

        return [
            'name' => $engagement->person->name,
            'email' => $engagement->person->email,
            'phone' => $engagement->person->phone,
            'status' => $engagement->status,
            'employment_type' => $engagement->employment_type,
            'hourly_pay' => $engagement->hourly_pay,
            'group_id' => $engagement->group_id,
            'role_id' => $engagement->role_id,
            ...$overrides,
        ];
    }
}
