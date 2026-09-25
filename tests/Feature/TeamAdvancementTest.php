<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamAdvancementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_team_member_details_support_role_title_and_optional_email(): void
    {
        $this->assertTrue(Schema::hasColumn('team_engagements', 'role_title'));

        $person = Person::query()->create(['name' => 'No Email', 'email' => null]);
        $this->assertNull($person->email);
    }

    public function test_advancement_columns_use_locked_statuses_and_full_filtered_results(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        foreach (range(1, 26) as $number) {
            $this->engagement(
                $event,
                "Volunteer {$number}",
                $number === 1 ? 'reviewing' : 'applied',
                'volunteer',
            );
        }
        $this->engagement($event, 'Paid Coordinator', 'hired', 'paid');

        $this->actingAs($user)
            ->get(route('team.advancement', [
                'search' => 'VOLUNTEER',
                'employment_types' => ['volunteer'],
                'view' => 'columns',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Team/Advancement')
                    ->where('statuses', ['applied', 'reviewing', 'hired', 'declined'])
                    ->where('filters.search', 'VOLUNTEER')
                    ->where('filters.employment_types', ['volunteer'])
                    ->where('filters.view', 'columns')
                    ->has('engagements.data', 26)
                    ->where('statusCounts.applied', 25)
                    ->where('statusCounts.reviewing', 1)
                    ->where('statusCounts.hired', 0)
                    ->where('statusCounts.declined', 0),
            );
    }

    public function test_advancement_list_is_paginated(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        foreach (range(1, 26) as $number) {
            $this->engagement($event, "Member {$number}");
        }

        $this->actingAs($user)
            ->get(route('team.advancement', ['view' => 'list']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('filters.view', 'list')
                    ->has('engagements.data', 25)
                    ->where('engagements.meta.total', 26),
            );
    }

    public function test_staff_can_add_a_paid_member_to_the_pipeline(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $group = Group::query()->create(['event_id' => $event->id, 'name' => 'Stage']);

        $response = $this->actingAs($user)->post(route('team.members.store', $event), [
            'name' => 'Morgan West',
            'email' => '',
            'phone' => '(604) 555-0142',
            'status' => 'applied',
            'employment_type' => 'paid',
            'role_title' => 'Gate supervisor',
            'hourly_pay' => '28.00',
            'group_id' => $group->id,
        ]);

        $engagement = TeamEngagement::query()->with('person')->sole();
        $response->assertRedirect(route('team.members.show', $engagement));
        $this->assertNull($engagement->person->email);
        $this->assertSame('Morgan West', $engagement->person->name);
        $this->assertSame('28.00', $engagement->hourly_pay);
        $this->assertSame($group->id, $engagement->group_id);
        $this->assertSame('Gate supervisor', $engagement->role_title);
    }

    public function test_the_same_person_cannot_be_added_to_one_event_twice(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $this->engagement($event, 'Existing Member');

        $this->actingAs($user)->post(route('team.members.store', $event), [
            'name' => 'Existing Member',
            'email' => 'EXISTING-MEMBER@example.test',
            'status' => 'applied',
            'employment_type' => 'volunteer',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('team_engagements', 1);
    }

    public function test_staff_can_move_a_member_to_any_locked_pipeline_status(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $engagement = $this->engagement($event, 'Sam Rivera');

        foreach (['reviewing', 'hired', 'declined', 'applied'] as $status) {
            $this->actingAs($user)
                ->patch(route('team.members.status.update', $engagement), ['status' => $status])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $engagement->fresh()->status);
        }
    }

    public function test_member_page_loads_and_details_update_with_one_event_group(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $engagement = $this->engagement($event, 'Taylor Brooks');
        $group = Group::query()->create(['event_id' => $event->id, 'name' => 'Main Stage']);

        $this->actingAs($user)
            ->get(route('team.members.show', $engagement))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Team/Member')
                    ->where('engagement.name', 'Taylor Brooks')
                    ->where('statuses', ['applied', 'reviewing', 'hired', 'declined'])
                    ->where('groups.0.name', 'Main Stage')
                    ->where('canWrite', true),
            );

        $this->actingAs($user)->put(route('team.members.update', $engagement), [
            'name' => 'Taylor Brooks',
            'email' => 'taylor@example.test',
            'phone' => '555-0100',
            'status' => 'hired',
            'employment_type' => 'volunteer',
            'role_title' => 'Stagehand',
            'hourly_pay' => null,
            'group_id' => $group->id,
        ])->assertRedirect(route('team.members.show', $engagement));

        $engagement->refresh();
        $this->assertSame('hired', $engagement->status);
        $this->assertSame('volunteer', $engagement->employment_type);
        $this->assertNull($engagement->hourly_pay);
        $this->assertSame($group->id, $engagement->group_id);
    }

    public function test_member_writes_validate_status_pay_and_event_group(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $foreignGroup = Group::query()->create([
            'event_id' => $this->event('Other Festival')->id,
            'name' => 'Foreign Group',
        ]);

        $this->actingAs($user)->post(route('team.members.store', $event), [
            'name' => 'Invalid Member',
            'status' => 'interview',
            'employment_type' => 'paid',
            'hourly_pay' => '',
            'group_id' => $foreignGroup->id,
        ])->assertSessionHasErrors(['status', 'hourly_pay', 'group_id']);

        $this->assertDatabaseCount('team_engagements', 0);
    }

    public function test_member_routes_reject_foreign_event_records(): void
    {
        [$user] = $this->userWithCompletedSetup();
        $foreign = $this->engagement($this->event('Other Festival'), 'Foreign Member');

        $this->actingAs($user)->get(route('team.members.show', $foreign))->assertNotFound();
        $this->actingAs($user)
            ->patch(route('team.members.status.update', $foreign), ['status' => 'hired'])
            ->assertNotFound();
    }

    public function test_locked_events_block_add_update_and_status_moves(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $engagement = $this->engagement($event, 'Locked Member');
        $event->lock();

        $this->actingAs($user)->get(route('team.members.create'))->assertForbidden();
        $this->actingAs($user)->post(route('team.members.store', $event), [])->assertForbidden();
        $this->actingAs($user)->put(route('team.members.update', $engagement), [])->assertForbidden();
        $this->actingAs($user)
            ->patch(route('team.members.status.update', $engagement), ['status' => 'hired'])
            ->assertForbidden();
    }

    public function test_member_mutations_require_manage_team_permission(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $engagement = $this->engagement($event, 'Permission Member');
        Gate::define('manage-team', fn (): bool => false);

        $this->actingAs($user)->get(route('team.members.create'))->assertForbidden();
        $this->actingAs($user)->post(route('team.members.store', $event), [])->assertForbidden();
        $this->actingAs($user)
            ->patch(route('team.members.status.update', $engagement), ['status' => 'hired'])
            ->assertForbidden();
    }

    /** @return array{User, Event} */
    private function userWithCompletedSetup(): array
    {
        $user = User::factory()->create();
        $event = $this->event();
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
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

    private function engagement(
        Event $event,
        string $name,
        string $status = 'applied',
        string $employmentType = 'volunteer',
    ): TeamEngagement {
        $person = Person::query()->create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
        ]);

        return TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'status' => $status,
            'employment_type' => $employmentType,
            'hourly_pay' => $employmentType === 'paid' ? 25 : null,
        ]);
    }
}
