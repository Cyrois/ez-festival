<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ShiftRole;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShiftConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_configure_page_lists_locations_templates_and_roles(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $role = $event->shiftRoles()->create(['name' => 'Supervisor']);
        $volunteer = $event->shiftRoles()->create(['name' => 'Volunteer']);
        $template = $event->shiftTemplates()->create([
            'location_id' => $location->id,
            'name' => 'Stage load-in',
        ]);
        $template->roleLines()->createMany([
            [
                'event_id' => $event->id,
                'shift_role_id' => $role->id,
                'headcount' => 1,
            ],
            [
                'event_id' => $event->id,
                'shift_role_id' => $volunteer->id,
                'headcount' => 4,
            ],
        ]);

        $this->actingAs($user)->get(route('team.configure'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Team/Configure')
                ->where('canWrite', true)
                ->where('locations.0.name', 'Main stage')
                ->where('locations.0.template_count', 1)
                ->where('templates.0.name', 'Stage load-in')
                ->where('templates.0.needs', 5)
                ->where('templates.0.roles.0.name', 'Supervisor')
                ->where('templates.0.roles.0.headcount', 1)
                ->where('shiftRoles.0.name', 'Supervisor'),
        );
    }

    public function test_shift_role_crud(): void
    {
        [$user, $event] = $this->createEventContext();

        $this->actingAs($user)->post(route('team.shift-roles.store', $event), [
            'name' => 'Bartender',
        ])->assertRedirect(route('team.configure'));

        $role = ShiftRole::query()->where('name', 'Bartender')->firstOrFail();
        $this->assertSame($event->id, $role->event_id);

        $this->actingAs($user)->put(route('team.shift-roles.update', [$event, $role]), [
            'name' => 'Bar lead',
        ])->assertRedirect(route('team.configure'));

        $this->assertSame('Bar lead', $role->fresh()->name);

        $this->actingAs($user)->delete(route('team.shift-roles.destroy', [$event, $role]))
            ->assertRedirect(route('team.configure'));

        $this->assertDatabaseMissing('shift_roles', ['id' => $role->id]);
    }

    public function test_shift_role_name_must_be_unique_per_event(): void
    {
        [$user, $event] = $this->createEventContext();
        $event->shiftRoles()->create(['name' => 'Supervisor']);

        $this->actingAs($user)->post(route('team.shift-roles.store', $event), [
            'name' => 'Supervisor',
        ])->assertSessionHasErrors('name');
    }

    public function test_shift_role_cannot_be_deleted_while_used_by_a_template(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);
        $template = $event->shiftTemplates()->create([
            'location_id' => $location->id,
            'name' => 'Gate open',
        ]);
        $template->roleLines()->create([
            'event_id' => $event->id,
            'shift_role_id' => $role->id,
            'headcount' => 2,
        ]);

        $this->actingAs($user)->delete(route('team.shift-roles.destroy', [$event, $role]))
            ->assertSessionHasErrors('shift_role');

        $this->assertDatabaseHas('shift_roles', ['id' => $role->id]);
    }

    public function test_shift_template_crud_with_role_lines_and_derived_needs(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $otherLocation = $event->locations()->create(['name' => 'Kitchen']);
        $supervisor = $event->shiftRoles()->create(['name' => 'Supervisor']);
        $volunteer = $event->shiftRoles()->create(['name' => 'Volunteer']);

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Show run',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $supervisor->id, 'headcount' => 1],
                ['shift_role_id' => $volunteer->id, 'headcount' => 3],
            ],
        ])->assertRedirect(route('team.configure'));

        $template = ShiftTemplate::query()->where('name', 'Show run')->firstOrFail();
        $this->assertSame(4, $template->needs());
        $this->assertCount(2, $template->roleLines);

        $this->actingAs($user)->put(route('team.shift-templates.update', [$event, $template]), [
            'name' => 'Show run evening',
            'location_id' => $otherLocation->id,
            'roles' => [
                ['shift_role_id' => $supervisor->id, 'headcount' => 2],
                ['shift_role_id' => $volunteer->id, 'headcount' => 5],
            ],
        ])->assertRedirect(route('team.configure'));

        $template->refresh();
        $this->assertSame('Show run evening', $template->name);
        $this->assertSame($otherLocation->id, $template->location_id);
        $this->assertSame(7, $template->needs());
        $this->assertCount(2, $template->roleLines);

        $this->actingAs($user)->delete(route('team.shift-templates.destroy', [$event, $template]))
            ->assertRedirect(route('team.configure'));

        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
        $this->assertDatabaseCount('shift_template_roles', 0);
    }

    public function test_template_location_must_belong_to_current_event(): void
    {
        [$user, $event] = $this->createEventContext();
        $otherEvent = Event::query()->create([
            'name' => 'Other fest',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-03',
            'timezone' => 'America/Vancouver',
        ]);
        $foreignLocation = $otherEvent->locations()->create(['name' => 'Foreign']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Bad location',
            'location_id' => $foreignLocation->id,
            'roles' => [
                ['shift_role_id' => $role->id, 'headcount' => 1],
            ],
        ])->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('shift_templates', 0);
    }

    public function test_template_role_must_belong_to_current_event(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $otherEvent = Event::query()->create([
            'name' => 'Other fest',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-03',
            'timezone' => 'America/Vancouver',
        ]);
        $foreignRole = $otherEvent->shiftRoles()->create(['name' => 'Foreign role']);

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Bad role',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $foreignRole->id, 'headcount' => 1],
            ],
        ])->assertSessionHasErrors('roles.0.shift_role_id');

        $this->assertDatabaseCount('shift_templates', 0);
    }

    public function test_template_headcount_must_be_at_least_one(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Zero headcount',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $role->id, 'headcount' => 0],
            ],
        ])->assertSessionHasErrors('roles.0.headcount');
    }

    public function test_template_requires_at_least_one_role_line(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'No roles',
            'location_id' => $location->id,
            'roles' => [],
        ])->assertSessionHasErrors('roles');
    }

    public function test_manage_shift_templates_denial_blocks_mutations(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);

        Gate::define('manage-shift-templates', fn (): bool => false);

        $this->actingAs($user)->get(route('team.configure'))->assertInertia(
            fn (Assert $page) => $page->where('canWrite', false),
        );

        $this->actingAs($user)->post(route('team.shift-roles.store', $event), [
            'name' => 'Denied',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Denied',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $role->id, 'headcount' => 1],
            ],
        ])->assertForbidden();
    }

    public function test_locked_event_blocks_shift_config_writes(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);
        $event->lock();

        $this->actingAs($user)->post(route('team.shift-roles.store', $event), [
            'name' => 'Locked',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('team.shift-templates.store', $event), [
            'name' => 'Locked',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $role->id, 'headcount' => 1],
            ],
        ])->assertForbidden();
    }

    public function test_template_from_another_event_is_not_found(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Gate']);
        $role = $event->shiftRoles()->create(['name' => 'Volunteer']);
        $otherEvent = Event::query()->create([
            'name' => 'Other fest',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-03',
            'timezone' => 'America/Vancouver',
        ]);
        $otherLocation = $otherEvent->locations()->create(['name' => 'Other']);
        $otherRole = $otherEvent->shiftRoles()->create(['name' => 'Volunteer']);
        $foreignTemplate = $otherEvent->shiftTemplates()->create([
            'location_id' => $otherLocation->id,
            'name' => 'Foreign',
        ]);
        $foreignTemplate->roleLines()->create([
            'event_id' => $otherEvent->id,
            'shift_role_id' => $otherRole->id,
            'headcount' => 1,
        ]);

        $this->actingAs($user)->put(route('team.shift-templates.update', [$event, $foreignTemplate]), [
            'name' => 'Hijack',
            'location_id' => $location->id,
            'roles' => [
                ['shift_role_id' => $role->id, 'headcount' => 2],
            ],
        ])->assertNotFound();
    }

    /** @return array{User, Event} */
    private function createEventContext(): array
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
