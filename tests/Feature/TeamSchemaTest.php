<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPatron;
use App\Models\Group;
use App\Models\PassAssignment;
use App\Models\PassType;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_team_schema_has_event_scoped_groups_and_engagement_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('groups', ['event_id', 'name']));
        $this->assertTrue(Schema::hasColumns('team_engagements', [
            'event_id',
            'person_id',
            'group_id',
            'status',
            'employment_type',
            'hourly_pay',
        ]));
        $this->assertTrue(Schema::hasColumn('pass_assignments', 'team_engagement_id'));
    }

    #[DataProvider('statusProvider')]
    public function test_team_engagement_accepts_each_pipeline_status(string $status): void
    {
        $engagement = TeamEngagement::query()->create([
            'event_id' => $this->event()->id,
            'person_id' => $this->person($status)->id,
            'status' => $status,
            'employment_type' => 'volunteer',
        ]);

        $this->assertSame($status, $engagement->status);
    }

    public function test_team_engagement_rejects_statuses_outside_the_pipeline(): void
    {
        $this->expectException(QueryException::class);

        DB::table('team_engagements')->insert([
            'event_id' => $this->event()->id,
            'person_id' => $this->person('invalid-status')->id,
            'status' => 'confirmed',
            'employment_type' => 'volunteer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_team_engagement_rejects_unknown_employment_types(): void
    {
        $this->expectException(QueryException::class);

        DB::table('team_engagements')->insert([
            'event_id' => $this->event()->id,
            'person_id' => $this->person('invalid-employment')->id,
            'status' => 'applied',
            'employment_type' => 'contractor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_person_can_only_have_one_group_assignment_per_event(): void
    {
        $event = $this->event();
        $person = $this->person('one-group');
        $firstGroup = Group::query()->create(['event_id' => $event->id, 'name' => 'Site']);
        $secondGroup = Group::query()->create(['event_id' => $event->id, 'name' => 'Production']);

        $engagement = TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'group_id' => $firstGroup->id,
            'status' => 'hired',
            'employment_type' => 'paid',
            'hourly_pay' => 28.50,
        ]);

        $this->assertSame('28.50', $engagement->hourly_pay);

        $this->expectException(QueryException::class);

        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'group_id' => $secondGroup->id,
            'status' => 'hired',
            'employment_type' => 'paid',
        ]);
    }

    public function test_an_engagement_cannot_use_a_group_from_another_event(): void
    {
        $engagementEvent = $this->event('Engagement festival');
        $otherEvent = $this->event('Other festival');
        $group = Group::query()->create(['event_id' => $otherEvent->id, 'name' => 'Site']);

        $this->expectException(QueryException::class);

        TeamEngagement::query()->create([
            'event_id' => $engagementEvent->id,
            'person_id' => $this->person('wrong-event')->id,
            'group_id' => $group->id,
            'status' => 'applied',
            'employment_type' => 'volunteer',
        ]);
    }

    public function test_deleting_an_event_cascades_its_groups_and_team_engagements(): void
    {
        $event = $this->event();
        $group = Group::query()->create(['event_id' => $event->id, 'name' => 'Site']);
        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $this->person('event-delete')->id,
            'group_id' => $group->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);

        $event->delete();

        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('team_engagements', 0);
    }

    public function test_pass_assignments_support_exactly_one_of_four_owner_types(): void
    {
        $event = $this->event();
        $engagement = TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $this->person('pass-owner')->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
        $passType = PassType::query()->create([
            'event_id' => $event->id,
            'name' => 'Crew',
        ]);

        $assignment = PassAssignment::query()->create([
            'pass_type_id' => $passType->id,
            'team_engagement_id' => $engagement->id,
            'person_id' => $engagement->person_id,
        ]);

        $this->assertSame($engagement->id, $assignment->teamEngagement->id);

        $patron = EventPatron::query()->create([
            'event_id' => $event->id,
            'person_id' => $this->person('patron-owner')->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('pass_assignments')->insert([
            'pass_type_id' => $passType->id,
            'team_engagement_id' => $engagement->id,
            'event_patron_id' => $patron->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_team_engagement_cascades_its_pass_assignments(): void
    {
        $event = $this->event();
        $engagement = TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $this->person('pass-cascade')->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);
        $passType = PassType::query()->create(['event_id' => $event->id, 'name' => 'Crew']);
        $engagement->passAssignments()->create(['pass_type_id' => $passType->id]);

        $engagement->delete();

        $this->assertDatabaseCount('pass_assignments', 0);
    }

    public function test_an_engagement_cannot_reference_a_missing_role(): void
    {
        $event = $this->event();
        $person = $this->person('missing-role');
        $role = Role::query()->create(['name' => 'Removed role']);
        $roleId = $role->id;
        $role->delete();

        $this->expectException(QueryException::class);

        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'role_id' => $roleId,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusProvider(): array
    {
        return collect(TeamEngagement::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => [$status]])
            ->all();
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

    private function person(string $suffix): Person
    {
        return Person::query()->create([
            'name' => 'Team Member '.$suffix,
            'email' => $suffix.'@example.test',
        ]);
    }
}
