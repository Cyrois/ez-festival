<?php

namespace Tests\Feature;

use App\Http\Resources\ShiftCopyResource;
use App\Models\Event;
use App\Models\Person;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Repositories\ShiftRepository;
use App\Services\MealAssignmentService;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamShiftSupervisorTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private Shift $shift;

    private ShiftAssignment $first;

    private ShiftAssignment $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->makeEvent('Festival');
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user->setCurrentEvent($this->event);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->shift = app(ShiftService::class)->create($this->event, [
            'name' => 'Gate shift', 'location_id' => $location->id,
            'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00',
        ]);
        $this->first = $this->assign($this->shift, $this->member('Alpha'));
        $this->second = $this->assign($this->shift, $this->member('Beta'));
        $this->actingAs($this->user);
    }

    public function test_create_saves_optional_supervisor_on_a_role_free_draft_assignment(): void
    {
        foreach ([null, -1, -2] as $key) {
            $name = 'Created '.($key ?? 'none');
            $this->post(route('team.shifts.store', $this->event), $this->payload([
                'name' => $name,
                'supervisor_key' => $key,
                'assignment_additions' => [
                    ['client_key' => -1, ...$this->addition($this->first->teamEngagement)],
                    ['client_key' => -2, ...$this->addition($this->second->teamEngagement)],
                ],
            ]))->assertSessionHasNoErrors();
            $created = Shift::where('name', $name)->sole();
            $this->assertSame($key === null ? 0 : 1, $created->assignments()->where('is_supervisor', true)->count());
            if ($key !== null) {
                $chosen = $key === -1 ? $this->first : $this->second;
                $this->assertSame($chosen->team_engagement_id, $created->supervisorAssignment->team_engagement_id);
                $this->assertNull($created->supervisorAssignment->role_id);
            }
        }
    }

    public function test_update_sets_replaces_clears_and_preserves_supervisor_when_omitted(): void
    {
        $break = $this->first->breaks()->create(['starts_at' => '2026-10-01T11:00', 'duration_minutes' => 15, 'sort_order' => 0]);
        foreach ([$this->first->id, $this->second->id, null, (string) $this->first->id] as $key) {
            $this->put($this->updateUrl(), $this->payload(['supervisor_key' => $key]))->assertSessionHasNoErrors();
            $this->assertSame($key === null ? null : (int) $key, $this->shift->fresh()->supervisorAssignment?->id);
            $this->assertSame('10:00', $this->first->fresh()->starts_at->format('H:i'));
            $this->assertModelExists($break);
        }
        $this->put($this->updateUrl(), $this->payload(['name' => 'Details changed']))->assertSessionHasNoErrors();
        $this->assertTrue($this->first->fresh()->is_supervisor);
    }

    public function test_supervisor_only_save_preserves_existing_meal_recipients(): void
    {
        $type = $this->event->mealTypes()->create(['name' => 'Lunch', 'starts_at' => '11:00', 'ends_at' => '13:00']);
        $meal = $this->event->meals()->create(['meal_type_id' => $type->id, 'name' => 'Lunch', 'date' => '2026-10-01', 'starts_at' => '11:00', 'ends_at' => '13:00']);
        $shiftMeal = $this->shift->meals()->create(['meal_id' => $meal->id]);
        app(MealAssignmentService::class)->syncShiftMeal($shiftMeal, [$this->first->id]);
        $columns = ['id', 'team_engagement_id', 'meal_id', 'is_active', 'claimed_at', 'shift_assignment_id'];
        $before = DB::table('meal_assignments')->get($columns)->toJson();
        $this->put($this->updateUrl(), $this->payload(['supervisor_key' => $this->first->id]))->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('meal_assignments')->get($columns)->toJson());
        $this->assertSame([$this->first->id], $shiftMeal->assignments()->pluck('shift_assignments.id')->all());
    }

    public function test_invalid_final_roster_references_fail_request_and_service_without_partial_writes(): void
    {
        $other = app(ShiftService::class)->create($this->event, $this->payload(['name' => 'Other']));
        $foreignEvent = $this->makeEvent('Other event');
        $foreignShift = $foreignEvent->shifts()->create([
            ...$this->payload(), 'location_id' => $foreignEvent->locations()->create(['name' => 'Foreign'])->id,
        ]);
        $foreign = $this->assign($foreignShift, $this->member('Foreign', $foreignEvent));
        $otherAssignment = $this->assign($other, $this->member('Other shift person'));
        $this->first->update(['is_supervisor' => true]);
        $before = $this->snapshot();
        foreach ([
            ['supervisor_key' => $otherAssignment->id],
            ['supervisor_key' => $foreign->id],
            ['supervisor_key' => -99],
            ['supervisor_key' => 0],
            ['supervisor_key' => [$this->first->id, $this->second->id]],
            ['supervisor_key' => $this->first->id, 'assignment_removals' => [$this->first->id]],
        ] as $change) {
            $data = $this->payload(['name' => 'Must not save', ...$change]);
            $this->put($this->updateUrl(), $data)->assertSessionHasErrors('supervisor_key');
            try {
                app(ShiftService::class)->update($this->shift, $data);
                $this->fail('The service must revalidate the supervisor.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('supervisor_key', $exception->errors());
            }
            $this->assertSame($before, $this->snapshot());
        }
        $this->put(route('team.shifts.update', [$foreignEvent, $this->shift]), $this->payload(['supervisor_key' => $this->first->id]))->assertNotFound();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_create_rejects_saved_ids_foreign_people_and_multiple_flags_without_creating_a_shift(): void
    {
        $foreign = $this->member('Wrong event', $this->makeEvent('Foreign'));
        foreach ([
            ['supervisor_key' => $this->first->id],
            ['supervisor_key' => [-1, -2]],
            ['supervisor_key' => -2, 'assignment_additions' => [['client_key' => -1, ...$this->addition($this->first->teamEngagement)]]],
            ['supervisor_key' => -1, 'assignment_additions' => [['client_key' => -1, ...$this->addition($foreign)]]],
            ['assignment_additions' => [
                ['client_key' => -1, ...$this->addition($this->first->teamEngagement), 'is_supervisor' => true],
                ['client_key' => -2, ...$this->addition($this->second->teamEngagement), 'is_supervisor' => true],
            ]],
        ] as $change) {
            $this->post(route('team.shifts.store', $this->event), $this->payload($change))->assertSessionHasErrors();
            $this->assertSame(1, $this->event->shifts()->count());
        }
    }

    public function test_removal_and_readdition_clear_supervisor_unless_explicitly_selected_again(): void
    {
        $this->first->update(['is_supervisor' => true]);
        $member = $this->first->teamEngagement;
        $this->put($this->updateUrl(), $this->payload([
            'supervisor_key' => null,
            'assignment_removals' => [$this->first->id],
            'assignment_additions' => [['client_key' => -1, ...$this->addition($member)]],
        ]))->assertSessionHasNoErrors();
        $replacement = $this->shift->assignments()->where('team_engagement_id', $member->id)->sole();
        $this->assertNotSame($this->first->id, $replacement->id);
        $this->assertFalse($replacement->is_supervisor);
        $this->put($this->updateUrl(), $this->payload(['supervisor_key' => $replacement->id]))->assertSessionHasNoErrors();
        $this->put($this->updateUrl(), $this->payload(['assignment_removals' => [$replacement->id]]))->assertSessionHasNoErrors();
        $this->assertNull($this->shift->fresh()->supervisorAssignment);
        $this->put($this->updateUrl(), $this->payload([
            'supervisor_key' => -2,
            'assignment_additions' => [['client_key' => -2, ...$this->addition($member)]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($member->id, $this->shift->fresh()->supervisorAssignment->team_engagement_id);
    }

    public function test_failed_assignment_save_retains_previous_supervisor_and_all_other_values(): void
    {
        $this->first->update(['is_supervisor' => true]);
        $before = $this->snapshot();
        $this->put($this->updateUrl(), $this->payload([
            'name' => 'Must roll back',
            'supervisor_key' => -1,
            'assignment_removals' => [$this->first->id],
            'assignment_additions' => [['client_key' => -1, ...$this->addition($this->second->teamEngagement)]],
        ]))->assertSessionHasErrors('assignment_additions.0.team_engagement_id');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_supervision_is_independent_of_personal_hours_eligibility_and_overlapping_shifts(): void
    {
        $other = app(ShiftService::class)->create($this->event, $this->payload(['name' => 'Overlapping']));
        $otherAssignment = $this->assign($other, $this->first->teamEngagement);
        $otherAssignment->update(['is_supervisor' => true]);
        $this->put($this->updateUrl(), $this->payload([
            'supervisor_key' => $this->first->id,
            'assignment_updates' => [['id' => $this->first->id, 'hours_mode' => 'custom', 'starts_at' => '2026-10-01T11:00', 'ends_at' => '2026-10-01T12:00']],
        ]))->assertSessionHasNoErrors();
        $this->first->teamEngagement->update(['status' => 'declined']);
        $this->put($this->updateUrl(), $this->payload(['supervisor_key' => $this->first->id]))->assertSessionHasNoErrors();
        $this->assertTrue($this->first->fresh()->is_supervisor);
        $this->assertTrue($otherAssignment->fresh()->is_supervisor);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('shift.supervisor_name', 'Alpha')->where('shift.assignments.0.is_supervisor', true)
            ->where('shift.assignments.0.starts_at', '2026-10-01T11:00')->has('shift.assignments.0.overlaps', 1));
    }

    public function test_copy_carries_supervisor_on_a_fresh_assignment_even_after_source_deletion(): void
    {
        $this->second->update(['is_supervisor' => true]);
        $this->get(route('team.shifts.copy', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('prefill.assignments.0.is_supervisor', false)->where('prefill.assignments.1.is_supervisor', true));
        $snapshot = (new ShiftCopyResource(app(ShiftService::class)->copyDraft($this->shift)))->resolve(request());
        $additions = [];
        $key = null;
        foreach ($snapshot['assignments'] as $index => $person) {
            $clientKey = -$index - 1;
            $additions[] = ['client_key' => $clientKey, 'extra' => true, 'team_engagement_id' => $person['team_engagement_id'], 'hours_mode' => 'full_shift'];
            if ($person['is_supervisor']) {
                $key = $clientKey;
            }
        }
        app(ShiftService::class)->delete($this->shift, 2);
        $this->post(route('team.shifts.store', $this->event), $this->payload(['assignment_additions' => $additions, 'supervisor_key' => $key]))->assertSessionHasNoErrors();
        $this->assertSame($this->second->team_engagement_id, Shift::sole()->supervisorAssignment->team_engagement_id);
        $this->assertNotSame($this->second->id, Shift::sole()->supervisorAssignment->id);
    }

    public function test_view_permissions_lock_gates_and_supervisor_do_not_grant_access(): void
    {
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $engagement = $this->event->teamEngagements()->where('person_id', $this->user->person_id)->sole();
        $assignment = $this->assign($this->shift, $engagement);
        $assignment->update(['is_supervisor' => true]);
        $this->get(route('team.shifts.show', $this->shift))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)->where('shift.supervisor_name', $this->user->name));
        $this->put($this->updateUrl(), $this->payload(['supervisor_key' => null]))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->payload(['supervisor_key' => null]))->assertForbidden();
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->get(route('team.shifts.show', $this->shift))->assertForbidden();
        $this->grantAdminAccess($this->user);
        $this->event->lock();
        $this->get(route('team.shifts.show', $this->shift))->assertOk();
        $this->put($this->updateUrl(), $this->payload(['supervisor_key' => null]))->assertForbidden();
        $this->post(route('team.shifts.store', $this->event), $this->payload(['supervisor_key' => null]))->assertForbidden();
        try {
            app(ShiftService::class)->update($this->shift, $this->payload(['supervisor_key' => null]));
            $this->fail('Locked services cannot change the supervisor.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertTrue($assignment->fresh()->is_supervisor);
    }

    public function test_list_grid_and_location_roster_show_only_the_allowed_supervisor_data_in_batches(): void
    {
        $this->first->update(['is_supervisor' => true]);
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->getJson(route('team.scheduling.shifts', ['length' => 10]))->assertOk()
            ->assertJsonPath('data.0.supervisor_name', 'Alpha')->assertJsonMissingPath('data.0.assignments');
        $this->getJson(route('team.scheduling.grid', ['date' => '2026-10-01']))->assertOk()
            ->assertJsonPath('data.0.shifts.0.supervisor_name', 'Alpha')->assertJsonMissingPath('data.0.shifts.0.assignments')
            ->assertDontSee('Beta', false)->assertDontSee($this->first->teamEngagement->person->email, false);
        $this->getJson(route('team.scheduling.roster', ['date' => '2026-10-01', 'location_id' => $this->shift->location_id]))->assertOk()
            ->assertJsonPath('data.0.supervisor_name', 'Alpha')->assertJsonPath('data.0.assignments.0.is_supervisor', true);
        for ($index = 0; $index < 26; $index++) {
            $shift = app(ShiftService::class)->create($this->event, $this->payload(['name' => 'Shift '.$index]));
            $this->assign($shift, $this->first->teamEngagement)->update(['is_supervisor' => true]);
        }
        DB::enableQueryLog();
        $result = app(ShiftRepository::class)->dataTable($this->event, '', 2, 'asc', 0, 10);
        $this->assertCount(10, $result['rows']);
        $this->assertSame(27, $result['total']);
        foreach ($result['rows'] as $shift) {
            $this->assertSame('Alpha', $shift->supervisorAssignment->teamEngagement->person->name);
        }
        $this->assertLessThan(15, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_database_constraint_defaults_false_and_refuses_two_or_null_supervisors(): void
    {
        $this->assertFalse($this->first->fresh()->is_supervisor);
        $this->first->update(['is_supervisor' => true]);
        foreach ([true, null] as $value) {
            try {
                DB::transaction(fn () => $this->second->update(['is_supervisor' => $value]));
                $this->fail('Invalid supervisor flags must be refused by the database.');
            } catch (QueryException) {
                $this->assertTrue($this->first->fresh()->is_supervisor);
                $this->assertFalse($this->second->fresh()->is_supervisor);
            }
        }
        $this->delete(route('team.shifts.assignments.destroy', [$this->event, $this->shift, $this->first]))->assertSessionHasNoErrors();
        $this->assertNull($this->shift->fresh()->supervisorAssignment);
        $this->second->update(['is_supervisor' => true]);
        app(ShiftService::class)->delete($this->shift, 1);
        $this->assertModelMissing($this->second);
    }

    public function test_migration_preserves_existing_assignments_and_defaults_them_to_no_supervisor(): void
    {
        $migration = require database_path('migrations/2026_10_09_000001_add_supervisor_to_shift_assignments.php');
        $this->first->update(['is_supervisor' => true]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('shift_assignments', 'is_supervisor'));
        $this->assertModelExists($this->first);
        $this->assertModelExists($this->second);
        $migration->up();
        $this->assertFalse($this->first->fresh()->is_supervisor);
        $this->assertFalse($this->second->fresh()->is_supervisor);
        $this->assertSame('10:00', $this->first->fresh()->starts_at->format('H:i'));
    }

    private function makeEvent(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02', 'timezone' => 'America/Vancouver']);
    }

    private function member(string $name, ?Event $event = null): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);

        return TeamEngagement::create(['event_id' => ($event ?? $this->event)->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function assign(Shift $shift, TeamEngagement $member): ShiftAssignment
    {
        return app(ShiftAssignmentService::class)->create($shift, $this->addition($member));
    }

    private function addition(TeamEngagement $member): array
    {
        return ['extra' => true, 'team_engagement_id' => $member->id, 'hours_mode' => 'full_shift'];
    }

    private function payload(array $changes = []): array
    {
        return ['name' => 'Gate shift', 'location_id' => $this->shift->location_id, 'starts_at' => '2026-10-01T10:00', 'ends_at' => '2026-10-01T14:00', ...$changes];
    }

    private function updateUrl(): string
    {
        return route('team.shifts.update', [$this->event, $this->shift]);
    }

    private function snapshot(): string
    {
        return json_encode([$this->shift->fresh()->toArray(), $this->shift->assignments()->get()->toArray()]);
    }
}
