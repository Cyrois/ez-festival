<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealClaim;
use App\Models\Person;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\EventService;
use App\Services\MealClaimService;
use App\Services\MealService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MealClaimsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private TeamEngagement $member;

    private Shift $shift;

    private Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = app(EventService::class)->create(['name' => 'Kitchen festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->actingAs($this->user);
        $this->member = $this->member('Jane Smith');
        $this->shift = $this->shift();
        $this->meal = app(MealService::class)->create($this->event, ['name' => 'Friday lunch', 'meal_type_id' => $this->event->mealTypes()->where('name', 'Lunch')->sole()->id, 'date' => '2026-10-01', 'starts_at' => '12:00', 'ends_at' => '14:00']);
        $this->grant($this->shift, $this->meal);
        // The event date differs from UTC; the meal window is not a claiming gate.
        $this->travelTo(Carbon::parse('2026-10-02 05:00', 'UTC'));
    }

    public function test_claim_records_actor_time_and_refreshes_counts_without_double_consumption(): void
    {
        $this->detail()->assertOk()->assertJsonPath('today', '2026-10-01')->assertJsonPath('counts.0.total', 1)
            ->assertJsonPath('counts.0.left', 1)->assertJsonPath('data.0.used', false);

        $this->claim()->assertCreated()->assertJsonPath('status', 'claimed');
        $claim = MealClaim::query()->sole();
        $this->assertSame($this->user->id, $claim->claimed_by);
        $this->assertTrue($claim->claimed_at->equalTo(now()));
        $this->assertNull($claim->warning_overridden_by);
        $this->detail()->assertJsonPath('data.0.used', true)->assertJsonPath('data.0.used_at', '22:00')
            ->assertJsonPath('counts.0.total', 1)->assertJsonPath('counts.0.left', 0);
        $this->claim(['confirm_warning' => true])->assertConflict()->assertJsonPath('status', 'already_used');
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_separate_shift_grants_require_warning_and_record_confirmation_only_when_needed(): void
    {
        $second = $this->shift('Second shift');
        $this->grant($second, $this->meal);
        $this->claim()->assertCreated();
        $this->claim(['source_shift_id' => $second->id])->assertConflict()->assertJsonPath('status', 'warning_required')
            ->assertJsonPath('message', __('meals.claim.warning', ['name' => 'Jane Smith', 'type' => 'Lunch', 'day' => 'today', 'time' => '22:00']));
        $this->assertDatabaseCount('meal_claims', 1);
        $this->claim(['source_shift_id' => $second->id, 'confirm_warning' => true])->assertCreated();
        $confirmed = MealClaim::query()->where('source_shift_id', $second->id)->sole();
        $this->assertSame($this->user->id, $confirmed->warning_overridden_by);
        $this->assertTrue($confirmed->warning_overridden_at->equalTo(now()));
        $this->detail()->assertJsonPath('recordsTotal', 2)->assertJsonPath('counts.0.left', 0);
    }

    public function test_removing_and_readding_assignment_or_meal_on_same_shift_keeps_used(): void
    {
        $this->claim()->assertCreated();
        $oldRow = $this->shift->meals()->sole();
        $this->shift->assignments()->delete();
        $oldRow->delete();
        $this->detail()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.used', true);
        $this->grant($this->shift, $this->meal);
        $this->assertNotSame($oldRow->id, $this->shift->meals()->sole()->id);
        $this->detail()->assertJsonPath('recordsTotal', 1)->assertJsonPath('counts.0.left', 0);
        $this->claim()->assertConflict()->assertJsonPath('status', 'already_used');
        $other = $this->shift('Different shift');
        $this->grant($other, $this->meal);
        $this->detail()->assertJsonPath('recordsTotal', 2)->assertJsonPath('counts.0.total', 2)->assertJsonPath('counts.0.left', 1);
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_removed_unused_grant_is_rejected_and_used_snapshot_survives_deleted_shift_and_meal_edits(): void
    {
        $other = $this->shift('Removed grant');
        $this->grant($other, $this->meal);
        $other->meals()->delete();
        $this->claim(['source_shift_id' => $other->id])->assertConflict()->assertJsonPath('status', 'unavailable');
        $this->claim()->assertCreated();
        $this->shift->meals()->delete();
        $this->shift->assignments()->delete();
        $this->shift->delete();
        app(MealService::class)->update($this->event, $this->meal, ['name' => 'Renamed', 'meal_type_id' => $this->meal->meal_type_id,
            'date' => '2026-10-02', 'starts_at' => '15:00', 'ends_at' => '16:00']);
        $this->detail()->assertJsonPath('data.0.name', 'Friday lunch')->assertJsonPath('data.0.starts_at', '12:00')
            ->assertJsonPath('data.0.shift_location', 'Gate')->assertJsonPath('data.0.date', '2026-10-01');
        $this->claim()->assertConflict()->assertJsonPath('status', 'already_used');
        $this->delete(route('meals.destroy', [$this->event, $this->meal]))->assertSessionHasErrors('meal');
        try {
            $this->member->delete();
            $this->fail('Used history must protect member deletion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_engagement_id', $exception->errors());
        }
    }

    public function test_overnight_carryover_is_inclusive_and_counts_and_warnings_use_original_day(): void
    {
        $this->shift->meals()->delete();
        $this->meal->update(['starts_at' => '23:30:00', 'ends_at' => '00:30:00']);
        $this->grant($this->shift, $this->meal);
        $second = $this->shift('Other overnight');
        $this->grant($second, $this->meal);
        $this->claim()->assertCreated();
        $tomorrow = app(MealService::class)->create($this->event, ['name' => 'Saturday lunch', 'meal_type_id' => $this->meal->meal_type_id,
            'date' => '2026-10-02', 'starts_at' => '12:00', 'ends_at' => '14:00']);
        $this->grant($this->shift, $tomorrow);
        $this->travelTo(Carbon::parse('2026-10-02 00:30:00', $this->event->timezone));
        $this->detail()->assertJsonPath('recordsTotal', 3)->assertJsonPath('counts.0.date', '2026-10-01')
            ->assertJsonPath('counts.0.total', 2)->assertJsonPath('counts.0.left', 1)
            ->assertJsonPath('counts.1.date', '2026-10-02')->assertJsonPath('counts.1.left', 1);
        $this->claim(['source_shift_id' => $second->id])->assertConflict()->assertJsonPath('status', 'warning_required');
        // A Friday claim does not warn for Saturday's meal of the same type.
        $this->claim(['meal_id' => $tomorrow->id])->assertCreated();
        $this->assertNull(MealClaim::query()->where('meal_id', $tomorrow->id)->sole()->warning_overridden_at);
        $this->claim(['source_shift_id' => $second->id, 'confirm_warning' => true])->assertCreated();
        $this->travelTo(Carbon::parse('2026-10-02 00:30:01', $this->event->timezone));
        $this->detail()->assertJsonPath('recordsTotal', 1)->assertJsonCount(1, 'counts');
        $this->claim()->assertUnprocessable()->assertJsonValidationErrors('meal_id');
    }

    public function test_form_requests_refuse_foreign_ids_unknown_ids_and_invalid_payloads(): void
    {
        $foreign = app(EventService::class)->create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $foreignMeal = $foreign->meals()->create(['name' => 'Other meal', 'meal_type_id' => $foreign->mealTypes()->firstOrFail()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00', 'ends_at' => '13:00']);
        foreach ([['meal_id' => $foreignMeal->id], ['meal_id' => 999999999], ['source_shift_id' => 0], ['confirm_warning' => 'yes']] as $changes) {
            $this->claim($changes)->assertUnprocessable()->assertJsonValidationErrors(array_key_first($changes));
        }
        $this->postJson(route('meals.claims.store', [$this->event, $this->member]), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['meal_id', 'source_shift_id']);
        $this->claim(['source_shift_id' => 999999999])->assertConflict()->assertJsonPath('status', 'unavailable');
        $this->getJson(route('meals.people.show', [$this->member]))->assertUnprocessable()->assertJsonValidationErrors(['draw', 'start', 'length']);
        $this->getJson(route('meals.people.index', ['page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->assertDatabaseCount('meal_claims', 0);
    }

    public function test_permission_revocation_and_locked_event_are_rechecked_by_service(): void
    {
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->detail()->assertOk()->assertJsonPath('can_claim', false);
        $this->claim()->assertForbidden();
        $this->assertServiceForbidden();
        $this->grantRoleAccess($this->user, ['meals.claim']);
        $this->detail()->assertOk()->assertJsonPath('can_claim', true);
        $this->user->person->teamEngagements()->where('event_id', $this->event->id)->firstOrFail()->role->update(['active' => false]);
        $this->claim()->assertForbidden();
        $this->assertServiceForbidden();
        $this->grantAdminAccess($this->user);
        $this->actingAs($this->user);
        Event::findOrFail($this->event->id)->lock();
        $this->detail()->assertOk()->assertJsonPath('is_locked', true)->assertJsonPath('can_claim', false);
        $this->claim()->assertForbidden();
        $this->assertServiceForbidden();
        $this->assertDatabaseCount('meal_claims', 0);
    }

    public function test_no_meal_person_has_empty_counts_and_members_must_be_hired_in_current_event(): void
    {
        $empty = $this->member('No meals');
        $this->detail($empty)->assertOk()->assertJsonPath('recordsTotal', 0)->assertJsonPath('counts', []);
        $empty->update(['status' => 'applied']);
        $this->detail($empty)->assertNotFound();
        $this->postJson(route('meals.claims.store', [$this->event, $empty]), ['meal_id' => $this->meal->id, 'source_shift_id' => $this->shift->id])->assertNotFound();
        $other = app(EventService::class)->create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $empty->update(['status' => 'hired', 'event_id' => $other->id]);
        $this->detail($empty)->assertNotFound();
        $this->postJson(route('meals.claims.store', [$this->event, $empty]), ['meal_id' => $this->meal->id, 'source_shift_id' => $this->shift->id])->assertNotFound();
    }

    public function test_people_lookup_is_private_paginated_and_unique_exact_barcode_selects_only_hired_current_event_team(): void
    {
        $this->code($this->member, 'WB-123');
        $this->lookup('jANE')->assertJsonPath('total', 1)->assertJsonPath('matched_id', null)->assertJsonPath('people.0.name', 'Jane Smith');
        $this->lookup('wb-123')->assertJsonPath('matched_id', $this->member->id)->assertJsonPath('people.0.codes.0', 'WB-123');
        $this->lookup('WB-12')->assertJsonPath('matched_id', null)->assertJsonPath('total', 1);
        $duplicate = $this->member('Another wearer');
        $this->code($duplicate, 'WB-123');
        $this->lookup('WB-123')->assertJsonPath('matched_id', null)->assertJsonPath('total', 2);
        $foreign = app(EventService::class)->create(['name' => 'Other festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $duplicate->update(['event_id' => $foreign->id]);
        $this->lookup('WB-123')->assertJsonPath('matched_id', $this->member->id)->assertJsonPath('total', 1);
        $foreignPass = $foreign->passTypes()->create(['name' => 'Other wristband']);
        $item = $foreign->entitlementItems()->create(['name' => 'Other wristband']);
        $assignment = $this->member->passAssignments()->create(['pass_type_id' => $foreignPass->id, 'person_id' => $this->member->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $foreign->locations()->create(['name' => 'Other gate'])->id,
            'issued_by' => $this->user->id, 'code' => 'OTHER-EVENT-CODE']);
        $this->lookup('OTHER-EVENT-CODE')->assertJsonPath('total', 0)->assertJsonPath('people', []);

        $nonHired = $this->member('Jane applied');
        $nonHired->update(['status' => 'applied']);
        $this->code($nonHired, 'SECRET-CODE');
        $this->lookup('SECRET-CODE')->assertJsonPath('people', [])->assertJsonPath('matched_id', null);
        $this->lookup('unknown')->assertJsonPath('people', [])->assertJsonPath('matched_id', null);
        $this->lookup('')->assertJsonPath('people', []);
        $literal = $this->member('Percent %_!');
        $this->lookup('%_!')->assertJsonPath('total', 1)->assertJsonPath('people.0.id', $literal->id);
        for ($i = 0; $i < 26; $i++) {
            $this->member('Roster '.$i);
        }
        $this->lookup('Roster')->assertJsonPath('total', 26)->assertJsonCount(25, 'people')->assertJsonPath('last_page', 2);
        $this->getJson(route('meals.people.index', ['search' => 'Roster', 'page' => 2]))->assertJsonCount(1, 'people');
        $this->grantRoleAccess($this->user, ['scheduling.view']);
        $this->lookup('Jane')->assertForbidden();
    }

    public function test_person_meals_are_server_paginated_with_full_counts(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->grant($this->shift('Shift '.$i), $this->meal);
        }
        $this->detail()->assertJsonCount(25, 'data')->assertJsonPath('recordsTotal', 27)->assertJsonPath('counts.0.total', 27);
        $this->getJson(route('meals.people.show', [$this->member, 'draw' => 2, 'start' => 25, 'length' => 25]))
            ->assertJsonCount(2, 'data')->assertJsonPath('draw', 2)->assertJsonPath('counts.0.left', 27);
    }

    public function test_unclaim_restores_only_the_selected_grant_and_allows_claiming_again(): void
    {
        $second = $this->shift('Second grant');
        $this->grant($second, $this->meal);
        $firstId = $this->claim()->json('claim_id');
        $secondId = $this->claim(['source_shift_id' => $second->id, 'confirm_warning' => true])->json('claim_id');
        $this->detail()->assertJsonPath('counts.0.left', 0);
        $this->unclaim($firstId)->assertOk()->assertJsonPath('status', 'unclaimed');
        $this->assertDatabaseMissing('meal_claims', ['id' => $firstId]);
        $this->assertDatabaseHas('meal_claims', ['id' => $secondId]);
        $this->detail()->assertJsonPath('counts.0.total', 2)->assertJsonPath('counts.0.left', 1);
        $this->claim()->assertConflict()->assertJsonPath('status', 'warning_required');
        $this->claim(['confirm_warning' => true])->assertCreated();
        $this->detail()->assertJsonPath('counts.0.left', 0);
        $this->assertDatabaseCount('meal_claims', 2);
    }

    public function test_unclaim_rejects_invalid_foreign_person_and_foreign_event_claims(): void
    {
        $claimId = $this->claim()->json('claim_id');
        $this->deleteJson(route('meals.claims.destroy', [$this->event, $this->member]), [])
            ->assertUnprocessable()->assertJsonValidationErrors('claim_id');
        foreach (['bad', 999999999] as $invalid) {
            $this->deleteJson(route('meals.claims.destroy', [$this->event, $this->member]), ['claim_id' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('claim_id');
        }
        $otherMember = $this->member('Other person');
        $this->unclaim($claimId, $otherMember)->assertUnprocessable()->assertJsonValidationErrors('claim_id');
        $foreign = app(EventService::class)->create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $otherMember->update(['event_id' => $foreign->id]);
        $this->unclaim($claimId, $otherMember)->assertNotFound();
        $this->deleteJson(route('meals.claims.destroy', [$foreign, $this->member]), ['claim_id' => $claimId])->assertNotFound();
        MealClaim::findOrFail($claimId)->update(['event_id' => $foreign->id]);
        $this->unclaim($claimId)->assertUnprocessable()->assertJsonValidationErrors('claim_id');
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_unclaim_obeys_permissions_locks_hired_status_and_day_rules(): void
    {
        $claimId = $this->claim()->json('claim_id');
        $this->grantRoleAccess($this->user, ['meals.view']);
        $this->unclaim($claimId)->assertForbidden();
        try {
            app(MealClaimService::class)->unclaim($this->event, $this->member, $this->user, $claimId);
            $this->fail('Service must recheck permission.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->grantAdminAccess($this->user);
        $this->actingAs($this->user);
        Event::findOrFail($this->event->id)->lock();
        $this->unclaim($claimId)->assertForbidden();
        try {
            app(MealClaimService::class)->unclaim($this->event, $this->member, $this->user, $claimId);
            $this->fail('Service must recheck the lock.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Event::findOrFail($this->event->id)->unlock();
        $this->member->update(['status' => 'applied']);
        $this->unclaim($claimId)->assertNotFound();
        $this->member->update(['status' => 'hired']);
        $this->travelTo(Carbon::parse('2026-10-02 12:00', $this->event->timezone));
        $this->unclaim($claimId)->assertUnprocessable()->assertJsonValidationErrors('meal_id');
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_unclaim_does_not_recreate_removed_grants_and_handles_a_stale_correction(): void
    {
        $claimId = $this->claim()->json('claim_id');
        $this->shift->meals()->sole()->assignments()->detach();
        $this->unclaim($claimId)->assertOk();
        $this->detail()->assertJsonPath('recordsTotal', 0)->assertJsonPath('counts', []);
        $this->unclaim($claimId)->assertUnprocessable()->assertJsonValidationErrors('claim_id');
        $result = app(MealClaimService::class)->unclaim($this->event, $this->member, $this->user, $claimId);
        $this->assertSame('already_unclaimed', $result['status']);
        $this->claim()->assertConflict()->assertJsonPath('status', 'unavailable');
        $this->grant($this->shift, $this->meal);
        $this->claim()->assertCreated();
        $this->assertDatabaseCount('meal_claims', 1);
    }

    private function unclaim(int $claimId, ?TeamEngagement $member = null): TestResponse
    {
        return $this->deleteJson(route('meals.claims.destroy', [$this->event, $member ?? $this->member]), ['claim_id' => $claimId]);
    }

    private function assertServiceForbidden(): void
    {
        try {
            app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $this->meal->id, $this->shift->id);
            $this->fail('Expected stale service authorization to fail.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function member(string $name): TeamEngagement
    {
        $person = Person::create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);

        return $this->event->teamEngagements()->create(['person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function shift(string $name = 'Gate shift'): Shift
    {
        $location = $this->event->locations()->firstOrCreate(['name' => 'Gate']);

        return $this->event->shifts()->create(['name' => $name, 'location_id' => $location->id,
            'starts_at' => '2026-10-01 19:00:00', 'ends_at' => '2026-10-02 05:00:00']);
    }

    private function grant(Shift $shift, Meal $meal): void
    {
        $role = Role::firstOrCreate(['name' => 'Crew']);
        $assignment = $shift->assignments()->firstOrCreate(['team_engagement_id' => $this->member->id],
            ['role_id' => $role->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $row = $shift->meals()->firstOrCreate(['meal_id' => $meal->id]);
        $row->assignments()->syncWithoutDetaching([$assignment->id]);
    }

    private function code(TeamEngagement $member, string $code): void
    {
        $pass = $this->event->passTypes()->firstOrCreate(['name' => 'Team']);
        $item = $this->event->entitlementItems()->firstOrCreate(['name' => 'Wristband']);
        $assignment = $member->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $member->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $this->shift->location_id, 'issued_by' => $this->user->id, 'code' => $code]);
    }

    private function lookup(string $search): TestResponse
    {
        return $this->getJson(route('meals.people.index', ['search' => $search]));
    }

    private function detail(?TeamEngagement $member = null): TestResponse
    {
        return $this->getJson(route('meals.people.show', [$member ?? $this->member, 'draw' => 1, 'start' => 0, 'length' => 25]));
    }

    private function claim(array $changes = []): TestResponse
    {
        return $this->postJson(route('meals.claims.store', [$this->event, $this->member]),
            ['meal_id' => $this->meal->id, 'source_shift_id' => $this->shift->id, ...$changes]);
    }
}
