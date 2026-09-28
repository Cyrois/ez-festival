<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\EventPatron;
use App\Models\PassAssignment;
use App\Models\PassTypeLabel;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamPassAssignmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_hired_member_receives_one_pass_with_holder_and_expected_entitlements(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $firstItem = $event->entitlementItems()->create(['name' => 'Wristband']);
        $secondItem = $event->entitlementItems()->create(['name' => 'Meal']);
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $passType->entitlements()->createMany([
            ['entitlement_item_id' => $firstItem->id, 'sort_order' => 0],
            ['entitlement_item_id' => $secondItem->id, 'sort_order' => 1],
        ]);

        $this->actingAs($user)
            ->put(
                route('team.members.update', $engagement),
                $this->updatePayload($engagement, [
                    ['pass_type_id' => $passType->id],
                ]),
            )
            ->assertSessionHas('success', __('team.advancement.toast.updated'));

        $assignment = PassAssignment::query()->sole();
        $this->assertSame($engagement->id, $assignment->team_engagement_id);
        $this->assertSame($engagement->person_id, $assignment->person_id);
        $this->assertSame(2, $assignment->expectedEntitlements()->count());
        $this->assertDatabaseCount('entitlement_adjustments', 0);
    }

    public function test_pass_without_entitlement_lines_can_be_given_and_removed(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Parking']);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['pass_type_id' => $passType->id],
            ]),
        )->assertSessionHasNoErrors();

        $assignment = PassAssignment::query()->sole();
        $this->assertSame(0, $assignment->expectedEntitlements()->count());

        $this->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, []),
        )->assertSessionHas('success', __('team.advancement.toast.updated'));

        $this->assertDatabaseCount('pass_assignments', 0);
    }

    public function test_non_hired_member_cannot_give_or_remove_passes_and_keeps_existing_passes(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        $engagement->update(['status' => 'reviewing']);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['id' => $assignment->id, 'pass_type_id' => $passType->id],
                ['pass_type_id' => $passType->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');
        $this->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, []),
        )->assertSessionHasErrors('pass_assignments');

        $this->assertDatabaseHas('pass_assignments', ['id' => $assignment->id]);
        $this->get(route('team.members.show', $engagement))->assertInertia(
            fn (Assert $page) => $page
                ->where('engagement.status', 'reviewing')
                ->has('engagement.pass_assignments', 1),
        );
    }

    public function test_capacity_counts_artist_vendor_patron_and_team_assignments(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'All Access', 'max_assignments' => 4]);

        $artist = Artist::query()->create(['name' => 'Artist Owner']);
        $artistEngagement = ArtistEngagement::query()->create([
            'artist_id' => $artist->id,
            'event_id' => $event->id,
        ]);
        $artistEngagement->passAssignments()->create(['pass_type_id' => $passType->id]);

        $vendor = Vendor::query()->create(['name' => 'Vendor Owner']);
        $vendorEngagement = VendorEngagement::query()->create([
            'vendor_id' => $vendor->id,
            'event_id' => $event->id,
        ]);
        $vendorEngagement->passAssignments()->create(['pass_type_id' => $passType->id]);

        $patron = EventPatron::query()->create([
            'event_id' => $event->id,
            'person_id' => $user->person_id,
        ]);
        $patron->passAssignments()->create(['pass_type_id' => $passType->id]);

        $otherTeamMember = $this->engagement($event, 'Other Team Member');
        $otherTeamMember->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $otherTeamMember->person_id,
        ]);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['pass_type_id' => $passType->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');

        $this->assertSame(4, $passType->assignments()->count());
    }

    public function test_foreign_event_pass_is_rejected(): void
    {
        [$user, , $engagement] = $this->teamContext();
        $foreignPass = $this->event('Other Festival')->passTypes()->create(['name' => 'Foreign']);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['pass_type_id' => $foreignPass->id],
            ]),
        )->assertSessionHasErrors('pass_assignments.0.pass_type_id');

        $this->assertDatabaseCount('pass_assignments', 0);
    }

    public function test_removing_unissued_pass_cascades_expected_entitlements(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);

        $this->actingAs($user)
            ->put(
                route('team.members.update', $engagement),
                $this->updatePayload($engagement, []),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pass_assignments', 0);
        $this->assertDatabaseCount('expected_entitlements', 0);
    }

    public function test_pass_with_any_issued_entitlement_cannot_be_removed(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        $expected = $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
        ]);
        $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $item->id,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(
                route('team.members.update', $engagement),
                $this->updatePayload($engagement, []),
            )
            ->assertSessionHasErrors('pass_assignments');

        $this->assertDatabaseHas('pass_assignments', ['id' => $assignment->id]);
    }

    public function test_saved_unissued_pass_type_must_be_removed_instead_of_changed(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $firstItem = $event->entitlementItems()->create(['name' => 'Wristband']);
        $secondItem = $event->entitlementItems()->create(['name' => 'Meal']);
        $firstPass = $event->passTypes()->create(['name' => 'Crew']);
        $firstPass->entitlements()->create([
            'entitlement_item_id' => $firstItem->id,
            'sort_order' => 0,
        ]);
        $secondPass = $event->passTypes()->create(['name' => 'Catering']);
        $secondPass->entitlements()->create([
            'entitlement_item_id' => $secondItem->id,
            'sort_order' => 0,
        ]);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $firstPass->id,
            'person_id' => $engagement->person_id,
        ]);
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $firstItem->id]);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['id' => $assignment->id, 'pass_type_id' => $secondPass->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');

        $this->assertSame($firstPass->id, $assignment->fresh()->pass_type_id);
        $this->assertDatabaseHas('expected_entitlements', [
            'pass_assignment_id' => $assignment->id,
            'entitlement_item_id' => $firstItem->id,
        ]);
        $this->assertDatabaseMissing('expected_entitlements', [
            'pass_assignment_id' => $assignment->id,
            'entitlement_item_id' => $secondItem->id,
        ]);
    }

    public function test_issued_pass_type_cannot_change_when_the_member_is_saved(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $firstPass = $event->passTypes()->create(['name' => 'Crew']);
        $secondPass = $event->passTypes()->create(['name' => 'Catering']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $firstPass->id,
            'person_id' => $engagement->person_id,
        ]);
        $expected = $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
        ]);
        $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $item->id,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['id' => $assignment->id, 'pass_type_id' => $secondPass->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');

        $this->assertSame($firstPass->id, $assignment->fresh()->pass_type_id);
    }

    public function test_locked_event_blocks_give_and_remove(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        $event->lock();

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, []),
        )->assertForbidden();

        $this->assertSame(1, $engagement->passAssignments()->count());
    }

    public function test_manage_team_permission_is_required_for_give_and_remove(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        Gate::define('manage-team', fn (): bool => false);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, []),
        )->assertForbidden();
    }

    public function test_team_route_rejects_another_members_and_artist_owned_assignments(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $other = $this->engagement($event, 'Other Member');
        $otherAssignment = $other->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $other->person_id,
        ]);
        $artist = Artist::query()->create(['name' => 'Artist Owner']);
        $artistEngagement = ArtistEngagement::query()->create([
            'artist_id' => $artist->id,
            'event_id' => $event->id,
        ]);
        $artistAssignment = $artistEngagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
        ]);

        $this->actingAs($user)->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['id' => $otherAssignment->id, 'pass_type_id' => $passType->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');
        $this->put(
            route('team.members.update', $engagement),
            $this->updatePayload($engagement, [
                ['id' => $artistAssignment->id, 'pass_type_id' => $passType->id],
            ]),
        )->assertSessionHasErrors('pass_assignments');

        $this->assertDatabaseHas('pass_assignments', ['id' => $otherAssignment->id]);
        $this->assertDatabaseHas('pass_assignments', ['id' => $artistAssignment->id]);
    }

    public function test_shared_destroy_route_does_not_handle_team_assignments(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);

        $this->actingAs($user)
            ->delete(route('pass-assignments.destroy', $assignment))
            ->assertNotFound();

        $this->assertDatabaseHas('pass_assignments', ['id' => $assignment->id]);
    }

    public function test_team_pass_assignments_have_no_immediate_write_routes(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $passType = $event->passTypes()->create(['name' => 'Crew']);

        $this->actingAs($user)
            ->post("/team/members/{$engagement->id}/pass-assignments", [
                'pass_type_id' => $passType->id,
            ])
            ->assertNotFound();
        $this->delete("/team/members/{$engagement->id}/pass-assignments/999")
            ->assertNotFound();

        $this->assertDatabaseCount('pass_assignments', 0);
    }

    public function test_changing_member_email_updates_team_assignment_holders(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $originalPersonId = $engagement->person_id;
        $passType = $event->passTypes()->create(['name' => 'Crew']);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $originalPersonId,
        ]);

        $this->actingAs($user)->put(route('team.members.update', $engagement), [
            'name' => 'Updated Member',
            'email' => 'updated-member@example.test',
            'phone' => '',
            'status' => 'hired',
            'employment_type' => 'volunteer',
            'hourly_pay' => null,
            'group_id' => null,
        ])->assertSessionHasNoErrors();

        $engagement->refresh();
        $this->assertNotSame($originalPersonId, $engagement->person_id);
        $this->assertSame($engagement->person_id, $assignment->fresh()->person_id);
    }

    public function test_member_page_exposes_assignment_issue_state_labels_and_event_pass_capacity(): void
    {
        [$user, $event, $engagement] = $this->teamContext();
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $passType = $event->passTypes()->create(['name' => 'Crew', 'max_assignments' => 2]);
        $label = PassTypeLabel::query()->create(['name' => 'Backstage', 'color' => 'teal']);
        $passType->labels()->attach($label);
        $assignment = $engagement->passAssignments()->create([
            'pass_type_id' => $passType->id,
            'person_id' => $engagement->person_id,
        ]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $item->id,
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)->get(route('team.members.show', $engagement))->assertInertia(
            fn (Assert $page) => $page
                ->where('engagement.pass_assignments.0.pass_name', 'Crew')
                ->where('engagement.pass_assignments.0.labels.0.name', 'Backstage')
                ->where('engagement.pass_assignments.0.expected_count', 1)
                ->where('engagement.pass_assignments.0.issued_count', 1)
                ->where('engagement.pass_assignments.0.issue_state', 'issued')
                ->where('engagement.pass_assignments.0.can_remove', false)
                ->where('passes.0.assignments_count', 1)
                ->where('passes.0.labels.0.name', 'Backstage'),
        );
    }

    /** @return array{User, Event, TeamEngagement} */
    private function teamContext(): array
    {
        $user = User::factory()->create();
        $event = $this->event();
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event, $this->engagement($event, 'Hired Member')];
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

    private function engagement(Event $event, string $name): TeamEngagement
    {
        $person = Person::query()->create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
        ]);

        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
    }

    /** @param array<int, array<string, mixed>> $passAssignments */
    private function updatePayload(TeamEngagement $engagement, array $passAssignments): array
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
            'pass_assignments' => $passAssignments,
        ];
    }
}
