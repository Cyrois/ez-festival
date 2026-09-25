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

class TeamGroupsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_groups_have_an_optional_description_column(): void
    {
        $this->assertTrue(Schema::hasColumn('groups', 'description'));
    }

    public function test_configure_lists_paginated_event_groups_with_member_counts(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $group = Group::query()->create([
            'event_id' => $event->id,
            'name' => 'Parking',
            'description' => 'Lot and shuttle operations',
        ]);
        $engagement = $this->engagement($event, 'Taylor Team');
        $engagement->update(['group_id' => $group->id]);

        $this->actingAs($user)->get(route('team.configure'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Team/Configure')
                ->where('event.id', $event->id)
                ->where('groups.data.0.name', 'Parking')
                ->where('groups.data.0.description', 'Lot and shuttle operations')
                ->where('groups.data.0.team_engagements_count', 1)
                ->where('filters.search', '')
                ->where('canManage', true),
        );
    }

    public function test_configure_searches_group_names_and_descriptions_case_insensitively(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        Group::query()->create([
            'event_id' => $event->id,
            'name' => 'Parking',
            'description' => 'Lot and shuttle operations',
        ]);
        Group::query()->create([
            'event_id' => $event->id,
            'name' => 'Kitchen',
            'description' => 'Meal preparation and service',
        ]);
        Group::query()->create([
            'event_id' => $this->event('Other Festival')->id,
            'name' => 'Foreign Kitchen',
            'description' => 'Meal service',
        ]);

        $this->actingAs($user)
            ->get(route('team.configure', ['search' => 'MEAL']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('filters.search', 'MEAL')
                    ->has('groups.data', 1)
                    ->where('groups.data.0.name', 'Kitchen'),
            );
    }

    public function test_staff_can_create_update_and_delete_an_event_group(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Parking',
            'description' => 'Lot and shuttle operations',
        ])->assertRedirect(route('team.configure'));

        $group = Group::query()->where('event_id', $event->id)->sole();

        $this->actingAs($user)->put(route('team.groups.update', [$event, $group]), [
            'name' => 'Site Operations',
            'description' => 'Parking, gates, and site logistics',
        ])->assertRedirect(route('team.configure'));

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'event_id' => $event->id,
            'name' => 'Site Operations',
            'description' => 'Parking, gates, and site logistics',
        ]);

        $this->actingAs($user)
            ->delete(route('team.groups.destroy', [$event, $group]))
            ->assertRedirect(route('team.configure'));

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_name_is_required_and_unique_within_the_event(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        Group::query()->create(['event_id' => $event->id, 'name' => 'Kitchen']);

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => '',
        ])->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Kitchen',
        ])->assertSessionHasErrors('name');
    }

    public function test_group_description_is_optional_and_limited(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Parking',
            'description' => str_repeat('x', 1001),
        ])->assertSessionHasErrors('description');

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Kitchen',
            'description' => null,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('groups', [
            'event_id' => $event->id,
            'name' => 'Kitchen',
            'description' => null,
        ]);
    }

    public function test_group_with_assigned_members_cannot_be_deleted(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $group = Group::query()->create(['event_id' => $event->id, 'name' => 'Stage']);
        $this->engagement($event, 'Morgan Member')->update(['group_id' => $group->id]);

        $this->actingAs($user)
            ->delete(route('team.groups.destroy', [$event, $group]))
            ->assertSessionHasErrors('group');

        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    public function test_group_routes_reject_cross_event_models(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $otherEvent = $this->event('Other Festival');
        $foreignGroup = Group::query()->create([
            'event_id' => $otherEvent->id,
            'name' => 'Foreign Group',
        ]);
        $this->actingAs($user)->put(
            route('team.groups.update', [$event, $foreignGroup]),
            ['name' => 'Changed'],
        )->assertNotFound();
    }

    public function test_locked_events_block_group_writes(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $event->lock();

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Parking',
        ])->assertForbidden();
    }

    public function test_a_non_primary_event_can_be_configured_when_it_is_writable(): void
    {
        [$user] = $this->userWithCompletedSetup();
        $otherEvent = $this->event('Other Festival');

        $this->actingAs($user)->post(route('team.groups.store', $otherEvent), [
            'name' => 'Kitchen',
        ])->assertRedirect(route('team.configure'));

        $this->assertDatabaseHas('groups', [
            'event_id' => $otherEvent->id,
            'name' => 'Kitchen',
        ]);
    }

    public function test_group_mutations_require_the_manage_team_permission(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        Gate::define('manage-team', fn (): bool => false);

        $this->actingAs($user)->post(route('team.groups.store', $event), [
            'name' => 'Parking',
        ])->assertForbidden();

        $this->assertDatabaseCount('groups', 0);
    }

    /**
     * @return array{User, Event}
     */
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
}
