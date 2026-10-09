<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Location;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Services\EntitlementConsumeService;
use App\Services\EventService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamCheckInTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_hired_team_rows_include_no_pass_and_all_group_role_fallbacks(): void
    {
        [, $event] = $this->context();
        $group = $event->groups()->create(['name' => 'Stage crew']);
        $role = Role::create(['name' => 'Supervisor', 'permissions' => ['team.view']]);
        foreach ([
            ['Both', $group->id, $role->id],
            ['Group only', $group->id, null],
            ['Neither', null, null],
            ['Role only', null, $role->id],
        ] as [$name, $groupId, $roleId]) {
            $member = $this->member($event, $name);
            $member->update(['group_id' => $groupId, 'role_id' => $roleId]);
        }
        $withPass = $this->member($event, 'Zero lines');
        $this->pass($withPass, 0);
        foreach (['applied', 'reviewing', 'declined'] as $status) {
            $member = $this->member($event, $status);
            $member->update(['status' => $status]);
            $this->pass($member);
        }
        $this->member($this->event('Other'), 'Foreign');

        $this->getJson(route('check-in.data', $this->paging(['type' => 'team'])))
            ->assertOk()->assertJsonPath('recordsTotal', 5)->assertJsonPath('recordsFiltered', 5)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.context', 'Stage crew | Supervisor')
            ->assertJsonPath('data.1.context', 'Stage crew')
            ->assertJsonPath('data.2.context', 'Team')
            ->assertJsonPath('data.3.context', 'Supervisor')
            ->assertJsonPath('data.0.has_pass', false)
            ->assertJsonPath('data.0.check_in_status', 'complete')
            ->assertJsonPath('data.4.has_pass', true)->assertJsonPath('data.4.expected', 0);
        $this->get(route('check-in.index', ['type' => 'team']))->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 5)->where('people.meta.total', 5));
    }

    public function test_mixed_list_keeps_each_domains_ledger_separate_for_the_same_person(): void
    {
        [, $event] = $this->context();
        $member = $this->member($event, 'Shared person');
        $this->pass($member, 2);
        $pass = $event->passTypes()->create(['name' => 'Other domain']);
        $artist = ArtistEngagement::factory()->for($event)->for(Artist::factory())->create(['status' => 'confirmed']);
        $vendor = VendorEngagement::create(['event_id' => $event->id, 'vendor_id' => Vendor::create(['name' => 'Vendor'])->id, 'status' => 'confirmed']);
        foreach ([$artist, $vendor] as $engagement) {
            $engagement->people()->attach($member->person_id);
            $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $member->person_id]);
        }
        $response = $this->getJson(route('check-in.data', $this->paging()))->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('recordsTotal', 3);
        $this->assertSame(['artist', 'team', 'vendor'], array_column($response->json('data'), 'type'));
        $response->assertJsonPath('data.1.expected', 2)->assertJsonPath('data.1.pass_name', 'Crew pass');
        $this->getJson(route('check-in.data', $this->paging(['type' => 'artist'])))->assertJsonCount(1, 'data');
        $this->getJson(route('check-in.data', $this->paging(['type' => 'patron'])))->assertJsonCount(0, 'data');
    }

    public function test_team_filters_and_code_search_preserve_full_ledger_and_server_paging(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Alex');
        $expected = $this->pass($member, 2);
        $second = $this->pass($member);
        $second->passAssignment->update(['pass_type_id' => $event->passTypes()->create(['name' => 'Second pass'])->id]);
        $expected->issuedEntitlement()->create(['entitlement_item_id' => $expected->entitlement_item_id, 'issued_by' => $user->id, 'code' => 'TEAM-100%_']);
        for ($i = 0; $i < 27; $i++) {
            $this->member($event, sprintf('Member %02d', $i));
        }
        $this->getJson(route('check-in.data', $this->paging(['type' => 'team', 'search' => 'team-100%_'])))
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.expected', 3)->assertJsonPath('data.0.issued', 1);
        $this->getJson(route('check-in.data', $this->paging(['type' => 'team', 'status' => 'partial'])))
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.person_id', $member->person_id);
        $this->getJson(route('check-in.data', $this->paging(['pass' => $expected->passAssignment->pass_type_id])))
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.expected', 2);
        $this->getJson(route('check-in.data', $this->paging(['search' => 'alex'])))
            ->assertJsonPath('recordsFiltered', 1);
        $this->getJson(route('check-in.data', $this->paging(['status' => 'not_started'])))->assertJsonCount(0, 'data');
        $this->getJson(route('check-in.data', $this->paging(['status' => 'complete', 'start' => 25])))
            ->assertJsonPath('recordsFiltered', 27)->assertJsonCount(2, 'data');
        $this->get(route('check-in.index', ['type' => 'team', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 3)->where('people.meta.total', 28));
    }

    public function test_shared_page_distinguishes_no_pass_from_a_zero_line_pass(): void
    {
        [, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $this->get(route('check-in.teams.show', [$member, 'person' => $member->person_id]))
            ->assertInertia(fn (Assert $page) => $page->component('CheckIn/Team')
                ->where('engagement.name', 'Maya')->where('engagement.type', 'team')
                ->has('engagement.people', 1)->where('engagement.people.0.has_pass', false)
                ->where('engagement.people.0.expected', 0)->missing('engagement.people.0.entitlements')->where('selectedPersonId', $member->person_id));
        $this->pass($member, 0);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('engagement.people.0.has_pass', true)->where('engagement.people.0.expected', 0)->missing('engagement.people.0.entitlements'));
    }

    public function test_issuance_decrements_selected_stock_and_blocks_pass_removal_and_repeated_issuance(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $location = $this->stock($event, $expected, 2);
        $other = $this->stock($event, $expected, 4, 'Other gate');
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', true)->where('engagement.people.0.expected', 1));
        $this->getJson($this->entitlementTable($member))->assertOk()->assertJsonCount(2, 'data.0.locations');
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id, 'code' => '  TEAM-42  '])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('issued_entitlements', ['expected_entitlement_id' => $expected->id,
            'location_id' => $location->id, 'code' => 'TEAM-42', 'issued_by' => $user->id]);
        $this->assertSame(1, (int) $expected->entitlementItem->adjustments()->where('location_id', $location->id)->sum('delta'));
        $this->assertSame(4, (int) $expected->entitlementItem->adjustments()->where('location_id', $other->id)->sum('delta'));
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])
            ->assertSessionHasErrors('expected_entitlement');
        $this->put(route('team.members.update', $member), [
            'name' => $member->person->name, 'email' => $member->person->email,
            'status' => 'hired', 'employment_type' => 'volunteer', 'pass_assignments' => [],
        ])->assertSessionHasErrors('pass_assignments');
        $this->assertModelExists($expected->passAssignment);
    }

    public function test_foreign_non_hired_and_mismatched_holder_records_are_refused(): void
    {
        [, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $location = $this->stock($event, $expected, 2);
        $foreign = $this->member($this->event('Foreign'), 'Foreign');
        $foreignExpected = $this->pass($foreign);
        $this->get(route('check-in.teams.show', $foreign))->assertNotFound();
        $this->post(route('check-in.issues.store', $foreignExpected), ['location_id' => $location->id])->assertNotFound();
        foreach (['applied', 'reviewing', 'declined'] as $status) {
            $member->update(['status' => $status]);
            $this->get(route('check-in.teams.show', $member))->assertNotFound();
            $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertNotFound();
        }
        $member->update(['status' => 'hired']);
        $expected->passAssignment->update(['person_id' => $foreign->person_id]);
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertNotFound();
        $expected->passAssignment->update(['person_id' => null]);
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertNotFound();
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    public function test_foreign_passes_and_items_never_enter_the_team_ledger(): void
    {
        [, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $foreignEvent = $this->event('Other');
        $foreignPass = $foreignEvent->passTypes()->create(['name' => 'Foreign pass']);
        $foreignItem = $foreignEvent->entitlementItems()->create(['name' => 'Foreign item']);
        $location = $this->stock($event, $expected, 2);
        $expected->passAssignment->update(['pass_type_id' => $foreignPass->id]);
        $this->getJson(route('check-in.data', $this->paging(['search' => 'Maya'])))
            ->assertJsonPath('data.0.has_pass', false)->assertJsonPath('data.0.expected', 0);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('engagement.people.0.has_pass', false)->where('engagement.people.0.expected', 0)->missing('engagement.people.0.entitlements'));
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertNotFound();
        $expected->passAssignment->update(['pass_type_id' => $event->passTypes()->firstOrFail()->id]);
        $expected->update(['entitlement_item_id' => $foreignItem->id]);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('engagement.people.0.has_pass', true)->where('engagement.people.0.expected', 0)->missing('engagement.people.0.entitlements'));
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertNotFound();
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    public function test_form_validation_stock_and_locked_event_gates(): void
    {
        [, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $location = $event->locations()->create(['name' => 'Empty gate']);
        $foreignLocation = $this->event('Other')->locations()->create(['name' => 'Other']);
        $this->post(route('check-in.issues.store', $expected), [])->assertSessionHasErrors('location_id');
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $foreignLocation->id])->assertSessionHasErrors('location_id');
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id, 'code' => str_repeat('X', 256)])->assertSessionHasErrors('code');
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertSessionHasErrors('quantity');
        $event->lock();
        $this->getJson(route('check-in.data', $this->paging(['type' => 'team'])))->assertOk()->assertJsonCount(1, 'data');
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertForbidden();
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    public function test_checkin_permissions_redact_team_info_without_requiring_team_view(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $member->person->update(['phone' => '555-private']);
        $expected = $this->pass($member);
        $location = $this->stock($event, $expected, 2);
        $this->grantRoleAccess($user, ['checkin.view']);
        $this->get(route('team.members.show', $member))->assertForbidden();
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', false)->where('memberUrl', null)->where('canViewShifts', false)->missing('checkInShifts')
            ->where('engagement.people.0.personal_info_hidden', true)
            ->missing('engagement.people.0.email')->missing('engagement.people.0.phone'));
        $response = $this->getJson(route('check-in.data', $this->paging(['search' => 'Maya'])))
            ->assertOk()->assertJsonPath('data.0.personal_info_hidden', true)->assertJsonMissingPath('data.0.subtitle');
        $this->assertStringNotContainsString($member->person->email, $response->getContent());
        $this->get(route('check-in.teams.show', $member), ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])
            ->assertOk()->assertDontSee($member->person->email)->assertDontSee('555-private');
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertForbidden();
        $this->grantRoleAccess($user, ['checkin.edit', 'artists.personal_info', 'vendors.personal_info']);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', true)->missing('engagement.people.0.email'));
        $this->grantRoleAccess($user, ['checkin.edit', 'team.edit']);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('engagement.people.0.email', $member->person->email)
            ->where('engagement.people.0.phone', '555-private')->where('memberUrl', route('team.members.show', $member)));
        $this->getJson(route('check-in.data', $this->paging(['search' => 'Maya'])))
            ->assertJsonPath('data.0.subtitle', $member->person->email);
        $this->grantRoleAccess($user, ['team.view']);
        $this->get(route('check-in.teams.show', $member))->assertForbidden();
        $this->getJson(route('check-in.data', $this->paging()))->assertForbidden();
    }

    public function test_team_page_independently_gates_member_link_contact_details_and_shifts(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $member->person->update(['phone' => '555-private']);
        $member->update(['group_id' => $event->groups()->create(['name' => 'Gate group'])->id,
            'role_id' => Role::create(['name' => 'Gate role', 'permissions' => []])->id]);
        $this->grantRoleAccess($user, ['checkin.view', 'team.view']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->component('CheckIn/Team')->where('engagement.group_name', 'Gate group')->where('engagement.role_name', 'Gate role')
            ->where('memberUrl', route('team.members.show', $member))
            ->where('canViewShifts', false)->missing('checkInShifts')
            ->missing('engagement.people.0.email')->missing('engagement.people.0.phone'));
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'shift_assignments')));
        DB::disableQueryLog();
        $this->grantRoleAccess($user, ['checkin.view', 'team.personal_info', 'scheduling.view']);
        $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('memberUrl', null)->where('canViewShifts', true)->has('checkInShifts', 0)
            ->where('engagement.people.0.email', $member->person->email)->where('engagement.people.0.phone', '555-private'));
    }

    public function test_last_two_and_next_five_shifts_use_assignment_hours_event_local_time_and_event_member_scope(): void
    {
        [, $event] = $this->context();
        $this->travelTo(now()->setDateTime(2026, 10, 9, 17, 0, 0)->utc());
        $member = $this->member($event, 'Maya');
        $otherMember = $this->member($event, 'Other');
        $role = Role::create(['name' => 'Gate crew']);
        $create = function (Event $event, TeamEngagement $member, string $start) use ($role) {
            $location = $event->locations()->firstOrCreate(['name' => 'Gate']);
            $shift = $event->shifts()->create(['name' => 'Gate shift', 'location_id' => $location->id,
                'starts_at' => '2026-10-09T08:00', 'ends_at' => '2026-10-10T23:00']);

            return $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $role->id,
                'starts_at' => $start, 'ends_at' => '2026-10-10T23:00']);
        };
        // 17:00 UTC is 10:00 local. Include the exact boundary and only the two most recent past starts.
        $create($event, $member, '2026-07-01T08:00');
        $past = $create($event, $member, '2026-07-10T02:00');
        $recent = $create($event, $member, '2026-10-09T09:59');
        $create($event, $otherMember, '2026-10-09T10:00');
        $create($event, $otherMember, '2026-10-09T09:59');
        $create($this->event('Foreign'), $member, '2026-10-09T10:00');
        $create($this->event('Foreign past'), $member, '2026-10-09T09:59');
        $ids = [];
        foreach ([16, 15, 14, 13, 12, 11, 10] as $hour) {
            $assignment = $create($event, $member, sprintf('2026-10-09T%02d:00', $hour));
            $ids[$hour] = $assignment->id;
        }
        foreach ([false, true] as $locked) {
            if ($locked) {
                $event->lock();
            }
            $this->get(route('check-in.teams.show', $member))->assertInertia(fn (Assert $page) => $page
                ->has('checkInShifts', 7)->where('checkInShifts.0.id', $past->id)
                ->where('checkInShifts.1.id', $recent->id)->where('checkInShifts.2.id', $ids[10])
                ->where('checkInShifts.6.id', $ids[14])->where('checkInShifts.2.location', 'Gate')
                ->where('checkInShifts.2.role_name', 'Gate crew')->where('checkInShifts.2.starts_at', '2026-10-09T10:00')
                ->where('checkInShifts.2.ends_at', '2026-10-10T23:00')->where('canWrite', ! $locked));
        }
    }

    public function test_service_rechecks_hired_status_after_models_have_been_loaded(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $location = $this->stock($event, $expected, 2);
        $expected->load('passAssignment.teamEngagement');
        $member->update(['status' => 'reviewing']);
        try {
            app(EntitlementConsumeService::class)->consume($expected, $user, $location->id);
            $this->fail('The stale hired member must not be consumed.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    public function test_event_deletion_removes_team_issuance_before_restricted_children(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $expected = $this->pass($member);
        $location = $this->stock($event, $expected, 2);
        $this->post(route('check-in.issues.store', $expected), ['location_id' => $location->id])->assertSessionHasNoErrors();
        app(EventService::class)->delete($event);
        $this->assertModelMissing($member);
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->assertDatabaseCount('expected_entitlements', 0);
        $this->assertDatabaseCount('pass_assignments', 0);
    }

    private function entitlementTable(TeamEngagement $member, array $filters = []): string
    {
        return route('check-in.entitlements', [...[
            'draw' => 1, 'start' => 0, 'length' => 25, 'type' => 'team',
            'engagement_id' => $member->id, 'person_id' => $member->person_id,
        ], ...$filters]);
    }

    public function test_entitlement_table_pages_searches_and_sorts_audited_rows_without_leaking_other_ledgers(): void
    {
        [$user, $event] = $this->context();
        $user->update(['name' => 'Gate operator']);
        $member = $this->member($event, 'Maya');
        $first = $this->pass($member, 30);
        $location = $this->stock($event, $first, 100);
        app(EntitlementConsumeService::class)->consume($first, $user, $location->id, 'PASS-100%_');
        $this->pass($this->member($event, 'Other'), 4);
        $this->pass($this->member($this->event('Foreign'), 'Foreign'), 5);
        $this->getJson($this->entitlementTable($member, ['order' => [['column' => 1, 'dir' => 'asc']]]))->assertOk()->assertJsonPath('recordsTotal', 30)
            ->assertJsonCount(25, 'data')->assertJsonPath('data.0.status', 'issued')
            ->assertJsonPath('data.0.issued.issued_by', 'Gate operator')->assertJsonPath('data.0.issued.code', 'PASS-100%_')
            ->assertJsonPath('data.0.issued.location', 'Gate')->assertJsonPath('data.1.issued', null)
            ->assertJsonMissingPath('data.0.issued.email');
        $this->getJson($this->entitlementTable($member, ['start' => 25]))->assertJsonCount(5, 'data');
        $this->getJson($this->entitlementTable($member, ['length' => -1]))
            ->assertOk()->assertJsonPath('recordsTotal', 30)->assertJsonCount(30, 'data');
        foreach (['Gate operator', 'PASS-100%_', 'issued'] as $search) {
            $this->getJson($this->entitlementTable($member, ['search' => ['value' => $search]]))
                ->assertJsonPath('recordsTotal', 30)->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $first->id);
        }
        $this->getJson($this->entitlementTable($member, ['order' => [['column' => 1, 'dir' => 'desc']]]))
            ->assertJsonPath('data.0.status', 'pending');
        $event->lock();
        $this->getJson($this->entitlementTable($member))->assertOk();
        $user->delete();
        // Preserve the historical issuance when its actor has been deleted.
        $viewer = User::factory()->create();
        $this->grantAdminAccess($viewer);
        $viewer->setCurrentEvent($event);
        $this->actingAs($viewer)->getJson($this->entitlementTable($member, ['search' => ['value' => 'PASS-100%_']]))
            ->assertOk()->assertJsonPath('data.0.issued.issued_by', null);
    }

    public function test_entitlement_table_refuses_invalid_scope_and_revoked_permissions(): void
    {
        [$user, $event] = $this->context();
        $member = $this->member($event, 'Maya');
        $this->pass($member);
        $foreign = $this->member($this->event('Foreign'), 'Foreign');
        $this->getJson($this->entitlementTable($foreign))->assertNotFound();
        $this->getJson($this->entitlementTable($member, ['person_id' => $foreign->person_id]))->assertNotFound();
        $this->getJson($this->entitlementTable($member, ['length' => -2]))->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->getJson($this->entitlementTable($member, ['type' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->grantRoleAccess($user, ['checkin.view']);
        $this->getJson($this->entitlementTable($member))->assertOk();
        $this->grantRoleAccess($user, ['team.view']);
        $this->getJson($this->entitlementTable($member))->assertForbidden();
        $this->grantAdminAccess($user);
        $member->update(['status' => 'reviewing']);
        $this->getJson($this->entitlementTable($member))->assertNotFound();
    }

    private function context(): array
    {
        $event = $this->event('Festival');
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($user);

        return [$user, $event];
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
    }

    private function member(Event $event, string $name): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => str($name)->slug().'@example.test']);

        return $event->teamEngagements()->create(['person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function pass(TeamEngagement $member, int $lines = 1): ?ExpectedEntitlement
    {
        $pass = $member->event->passTypes()->firstOrCreate(['name' => 'Crew pass']);
        $assignment = $member->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $member->person_id]);
        $item = $member->event->entitlementItems()->firstOrCreate(['name' => 'Wristband']);
        $expected = null;
        for ($i = 0; $i < $lines; $i++) {
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        }

        return $expected;
    }

    private function stock(Event $event, ExpectedEntitlement $expected, int $quantity, string $name = 'Gate'): Location
    {
        $location = $event->locations()->create(['name' => $name]);
        $expected->entitlementItem->adjustments()->create(['location_id' => $location->id, 'delta' => $quantity]);

        return $location;
    }

    private function paging(array $filters = []): array
    {
        return [...['draw' => 1, 'start' => 0, 'length' => 25, 'type' => 'all'], ...$filters];
    }
}
