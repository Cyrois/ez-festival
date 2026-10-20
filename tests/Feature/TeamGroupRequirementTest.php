<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\GlobalTeamService;
use App\Services\ShiftAssignmentService;
use App\Services\TeamEngagementService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamGroupRequirementTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $this->group = $this->event->groups()->create(['name' => 'Gate']);
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->user);
    }

    public function test_applicants_can_be_ungrouped_but_create_and_full_update_require_group_when_hired(): void
    {
        $data = ['name' => 'Applicant', 'email' => 'applicant@example.test', 'status' => 'applied', 'employment_type' => 'volunteer'];
        $this->post(route('team.members.store', $this->event), $data)->assertSessionHasNoErrors();
        $member = TeamEngagement::sole();
        $this->assertNull($member->group_id);
        $this->put(route('team.members.update', $member), [...$data, 'status' => 'hired'])->assertSessionHasErrors('group_id');
        $this->post(route('team.members.store', $this->event), [...$data, 'email' => 'new@example.test', 'status' => 'hired'])->assertSessionHasErrors('group_id');
        $this->put(route('team.members.update', $member), [...$data, 'status' => 'hired', 'group_id' => $this->group->id])->assertSessionHasNoErrors();
        $this->assertSame('hired', $member->fresh()->status);
        $this->assertSame($this->group->id, $member->fresh()->group_id);
        $this->get(route('team.members.show', $member))->assertInertia(fn (Assert $page) => $page->where('engagement.has_shift_assignments', false));
    }

    public function test_status_shortcut_and_service_require_staff_to_assign_group_first(): void
    {
        $member = $this->applicant();
        $this->patch(route('team.members.status.update', $member), ['status' => 'hired'])->assertSessionHasErrors('status');
        $this->validationError(fn () => app(TeamEngagementService::class)->updateStatus($member, 'hired'), 'status');
        $this->validationError(fn () => app(TeamEngagementService::class)->create($this->event, [
            'name' => 'Direct', 'email' => 'direct@example.test', 'status' => 'hired', 'employment_type' => 'volunteer',
        ]), 'group_id');
        $this->assertDatabaseMissing('people', ['email' => 'direct@example.test']);
        $member->update(['group_id' => $this->group->id]);
        $this->patch(route('team.members.status.update', $member), ['status' => 'hired'])->assertSessionHasNoErrors();
        $this->assertSame('hired', $member->fresh()->status);
    }

    public function test_foreign_group_locked_event_and_permission_fail_without_changes(): void
    {
        $member = $this->applicant();
        $other = Event::create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $foreignGroup = $other->groups()->create(['name' => 'Gate']);
        $data = $this->payload($member, ['status' => 'hired', 'group_id' => $foreignGroup->id]);
        $this->put(route('team.members.update', $member), $data)->assertSessionHasErrors('group_id');
        $this->validationError(fn () => app(TeamEngagementService::class)->update($member, $this->user, $data), 'group_id');
        $data['group_id'] = $this->group->id;
        $this->event->lock();
        $this->put(route('team.members.update', $member), $data)->assertForbidden();
        try {
            app(TeamEngagementService::class)->update($member, $this->user, $data);
            $this->fail('Locked event must refuse service writes.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->event->unlock();
        $this->grantRoleAccess($this->user, ['team.view']);
        $this->put(route('team.members.update', $member), $data)->assertForbidden();
        $this->assertNull($member->fresh()->group_id);
        $this->assertSame('applied', $member->fresh()->status);
    }

    public function test_saved_assignments_keep_their_group_after_status_change_and_partial_edits_preserve_it(): void
    {
        $member = $this->applicant();
        $member->update(['group_id' => $this->group->id, 'status' => 'hired']);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $shift = $this->event->shifts()->create(['location_id' => $location->id, 'starts_at' => '2026-10-01 08:00', 'ends_at' => '2026-10-01 12:00']);
        $data = ['team_engagement_id' => $member->id, 'hours_mode' => 'full_shift', 'extra' => true];
        app(ShiftAssignmentService::class)->create($shift, $data);
        $this->post(route('team.shifts.assignments.store', [$this->event, $shift]), $data)->assertSessionHasErrors('team_engagement_id');
        $member->update(['status' => 'declined']);
        $payload = $this->payload($member, ['group_id' => null]);
        $this->put(route('team.members.update', $member), $payload)->assertSessionHasErrors('group_id');
        $this->validationError(fn () => app(TeamEngagementService::class)->update($member, $this->user, $payload), 'group_id');
        $this->delete(route('team.groups.destroy', [$this->event, $this->group]))->assertSessionHasErrors('group');
        $this->get(route('team.members.show', $member))->assertInertia(fn (Assert $page) => $page->where('engagement.has_shift_assignments', true));
        $this->grantRoleAccess($this->user, ['team.notes.add']);
        $this->put(route('team.members.update', $member), ['notes' => [['body' => 'A note']]])->assertSessionHasNoErrors();
        $this->assertSame($this->group->id, $member->fresh()->group_id);
        $this->assertCount(1, $member->notes);
    }

    public function test_global_team_cannot_bypass_hiring_rule_and_can_hire_an_existing_grouped_member(): void
    {
        $role = Role::create(['name' => 'Staff']);
        $data = ['name' => 'Global', 'email' => 'global@example.test', 'can_log_in' => false, 'status' => 'hired',
            'event_access' => [['event_id' => $this->event->id, 'role_id' => $role->id]]];
        $this->post(route('settings.team.store'), $data)->assertSessionHasErrors('status');
        $this->validationError(fn () => app(GlobalTeamService::class)->create($data, $this->user), 'status');
        $this->assertDatabaseMissing('people', ['email' => $data['email']]);
        $member = $this->applicant();
        $update = ['name' => $member->person->name, 'can_log_in' => false,
            'event_access' => [['event_id' => $this->event->id, 'role_id' => $role->id, 'status' => 'hired']]];
        $this->put(route('settings.team.update', $member->person), $update)->assertSessionHasErrors('event_access.0.status');
        $this->validationError(fn () => app(GlobalTeamService::class)->update($member->person, $update, $this->user), 'event_access.0.status');
        $member->update(['group_id' => $this->group->id]);
        $this->put(route('settings.team.update', $member->person), $update)->assertSessionHasNoErrors();
        $this->assertSame('hired', $member->fresh()->status);
    }

    public function test_database_guards_insert_and_update_and_migration_rollback_preserves_relations_and_indexes(): void
    {
        $member = $this->applicant();
        foreach ([fn () => DB::table('team_engagements')->where('id', $member->id)->update(['status' => 'hired']),
            fn () => DB::table('team_engagements')->insert(['event_id' => $this->event->id, 'person_id' => $this->user->person_id, 'status' => 'hired'])] as $write) {
            try {
                DB::transaction($write);
                $this->fail('Database must reject an ungrouped hired member.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('team_engagements_hired_group_check', $error->getMessage());
            }
        }
        $indexes = Schema::getIndexes('team_engagements');
        $member->notes()->create(['body' => 'Preserved', 'user_id' => $this->user->id]);
        $migration = require database_path('migrations/2026_10_18_000001_require_group_for_hired_team_members.php');
        $migration->down();
        $migration->up();
        $this->assertSame($indexes, Schema::getIndexes('team_engagements'));
        $this->assertSame('Preserved', $member->notes()->sole()->body);
        $this->assertNull($member->fresh()->group_id);
        $member->update(['group_id' => $this->group->id, 'status' => 'hired']);
        $this->assertSame('hired', $member->fresh()->status);
    }

    public function test_scheduling_defends_against_legacy_ungrouped_members_and_migration_never_invents_a_group(): void
    {
        $migration = require database_path('migrations/2026_10_18_000001_require_group_for_hired_team_members.php');
        $migration->down();
        $member = $this->applicant();
        $member->update(['status' => 'hired']);
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $shift = $this->event->shifts()->create(['location_id' => $location->id, 'starts_at' => '2026-10-01 08:00', 'ends_at' => '2026-10-01 12:00']);
        $data = ['team_engagement_id' => $member->id, 'hours_mode' => 'full_shift', 'extra' => true];
        $this->post(route('team.shifts.assignments.store', [$this->event, $shift]), $data)->assertSessionHasErrors('team_engagement_id');
        $this->validationError(fn () => app(ShiftAssignmentService::class)->create($shift, $data), 'team_engagement_id');
        $this->assertDatabaseCount('shift_assignments', 0);
        try {
            $migration->up();
            $this->fail('Migration must refuse ungrouped hired members.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Assign groups', $error->getMessage());
        }
        $this->assertNull($member->fresh()->group_id);
        $this->assertDatabaseCount('groups', 1);
        $member->update(['group_id' => $this->group->id]);
        $migration->up();
        app(ShiftAssignmentService::class)->create($shift, $data);
        $this->assertDatabaseCount('shift_assignments', 1);
        $this->event->lock();
        $this->post(route('team.shifts.assignments.store', [$this->event, $shift]), $data)->assertForbidden();
    }

    private function applicant(): TeamEngagement
    {
        return TeamEngagement::create(['event_id' => $this->event->id, 'status' => 'applied',
            'person_id' => Person::create(['name' => 'Applicant', 'email' => fake()->unique()->safeEmail()])->id]);
    }

    private function payload(TeamEngagement $member, array $overrides = []): array
    {
        return ['name' => $member->person->name, 'email' => $member->person->email, 'status' => $member->status,
            'employment_type' => 'volunteer', 'group_id' => $member->group_id, ...$overrides];
    }

    private function validationError(callable $write, string $field): void
    {
        try {
            $write();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }
}
