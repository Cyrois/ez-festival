<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Services\EventService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealOverrideService;
use App\Services\MealService;
use App\Support\OrganizationContext;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MealOverridesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private TeamEngagement $member;

    private Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = app(EventService::class)->create(['name' => 'Override festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->user = User::factory()->create();
        $this->grantAdminAccess($this->user);
        $this->user->setCurrentEvent($this->event);
        $this->actingAs($this->user);
        $this->member = $this->member();
        $this->meal = $this->meal('Friday lunch');
        $this->travelTo(Carbon::parse('2026-10-01 13:10', $this->event->timezone));
    }

    public function test_walk_up_override_is_given_and_claimed_atomically_with_persistent_origin(): void
    {
        $this->grantRoleAccess($this->user, ['meals.override']);
        $id = $this->give()->assertCreated()->assertJsonPath('status', 'overridden')->json('assignment_id');
        $assignment = MealAssignment::findOrFail($id);
        $this->assertTrue($assignment->is_override);
        $this->assertSame($this->user->id, $assignment->override_given_by);
        $this->assertSame($this->user->id, $assignment->claimed_by);
        $this->assertNotNull($assignment->claimed_at);
        $this->assertNotNull($assignment->claim_token);
        $this->assertNull($assignment->source_shift_id);
        $this->assertNull($assignment->shift_meal_id);
        $this->assertNull($assignment->warning_overridden_at);
        $this->detail()->assertJsonPath('data.0.used', true)->assertJsonPath('data.0.is_override', true)
            ->assertJsonPath('data.0.used_at', '13:10')->assertJsonPath('data.0.override_at', '13:10')
            ->assertJsonPath('counts.0.total', 1)->assertJsonPath('counts.0.left', 0)->assertJsonPath('counts.0.overrides', 1);
        $this->assertSame([], app(MealEntitlementQuery::class)->projected($this->event)->all());
    }

    public function test_give_without_claim_preserves_origin_and_can_be_claimed_later(): void
    {
        $this->grantRoleAccess($this->user, ['meals.override']);
        $id = $this->postJson(route('meals.overrides.store', [$this->event, $this->member]), [
            'meal_id' => $this->meal->id, 'confirmed' => true, 'claim' => false,
        ])->assertCreated()->assertJsonPath('status', 'overridden')->json('assignment_id');
        $assignment = MealAssignment::findOrFail($id);
        $this->assertTrue($assignment->is_override);
        $this->assertSame($this->user->id, $assignment->override_given_by);
        $this->assertNotNull($assignment->override_given_at);
        $this->assertNull($assignment->claimed_at);
        $this->assertNull($assignment->claimed_by);
        $this->assertNull($assignment->claim_token);
        $this->assertNull($assignment->source_shift_id);
        $this->detail()->assertJsonPath('data.0.used', false)->assertJsonPath('counts.0.left', 1)->assertJsonPath('counts.0.overrides', 0);
        $this->give()->assertConflict()->assertJsonPath('status', 'unused_meal');
        $this->postJson(route('meals.overrides.store', [$this->event, $this->member]), [
            'meal_id' => $this->meal->id, 'confirmed' => true, 'claim' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('claim');
        $this->grantRoleAccess($this->user, ['meals.claim']);
        $this->postJson(route('meals.claims.store', [$this->event, $this->member]), ['assignment_id' => $id])->assertCreated();
        $this->detail()->assertJsonPath('data.0.used', true)->assertJsonPath('counts.0.left', 0)->assertJsonPath('counts.0.overrides', 1);
    }

    public function test_unused_grants_of_the_same_type_block_every_meal_of_that_type_and_full_counts_are_used(): void
    {
        $otherLunch = $this->meal('Second lunch');
        for ($i = 0; $i < 26; $i++) {
            app(MealAssignmentService::class)->assign($this->event, $this->member, $otherLunch);
        }
        $this->mealOptions()->assertJsonPath('data.0.available', false)->assertJsonPath('data.1.available', false);
        $this->give()->assertConflict()->assertJsonPath('status', 'unused_meal');
        $this->assertSame(0, MealAssignment::where('is_override', true)->count());
        // Another day and a retired grant do not block today's override.
        MealAssignment::query()->update(['is_active' => false]);
        $tomorrow = $this->meal('Tomorrow lunch', '2026-10-02');
        app(MealAssignmentService::class)->assign($this->event, $this->member, $tomorrow);
        $this->give()->assertCreated();
    }

    public function test_exhausted_normal_grants_allow_multiple_overrides_and_used_counts_include_them(): void
    {
        $normal = app(MealAssignmentService::class)->assign($this->event, $this->member, $this->meal);
        $this->assertFalse($normal->fresh()->is_override);
        app(MealClaimService::class)->claim($this->event, $this->member, $this->user, $normal->id);
        $this->mealOptions()->assertJsonPath('data.0.available', true)->assertJsonPath('data.0.extra', true);
        $this->give()->assertCreated();
        $this->give()->assertCreated();
        $this->detail()->assertJsonPath('counts.0.total', 3)->assertJsonPath('counts.0.left', 0)
            ->assertJsonPath('counts.0.overrides', 2)->assertJsonPath('data.0.is_override', false)
            ->assertJsonPath('data.1.is_override', true)->assertJsonPath('data.2.is_override', true);
        $this->assertSame(3, MealAssignment::whereNotNull('claimed_at')->count());
        $this->assertSame(1, app(MealEntitlementQuery::class)->projected($this->event)->get($this->meal->id));
    }

    public function test_claim_meals_does_not_grant_override_and_removal_is_separate(): void
    {
        $this->grantRoleAccess($this->user, ['meals.claim']);
        $this->give()->assertForbidden();
        $this->mealOptions()->assertForbidden();
        $this->detail()->assertJsonPath('can_override', false)->assertJsonPath('can_remove_override', false);
        $this->assertForbiddenService(fn () => app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $this->meal->id));
        $this->grantRoleAccess($this->user, ['meals.override']);
        $id = $this->give()->assertCreated()->json('assignment_id');
        $this->remove($id)->assertForbidden();
        $this->assertForbiddenService(fn () => app(MealOverrideService::class)->remove($this->event, $this->member, $this->user, $id));
        $this->grantRoleAccess($this->user, ['meals.undo_claim']);
        $this->give()->assertForbidden();
        $this->detail()->assertOk()->assertJsonPath('can_remove_override', true);
        $this->assertSame(['meals.override', 'meals.view'], Permissions::expand(['meals.override']));
        $this->assertSame(['meals.undo_claim', 'meals.view'], Permissions::expand(['meals.undo_claim']));
    }

    public function test_roles_can_save_the_new_permissions_and_revocation_is_immediate(): void
    {
        $this->get(route('settings.roles.create'))->assertOk();
        $this->post(route('settings.roles.store'), ['name' => 'Kitchen overrides', 'permissions' => ['meals.override', 'meals.undo_claim']])->assertSessionHasNoErrors();
        $this->grantRoleAccess($this->user, ['meals.override']);
        $this->mealOptions()->assertOk();
        $this->user->person->teamEngagements()->where('event_id', $this->event->id)->sole()->role->update(['active' => false]);
        $this->give()->assertForbidden();
        $this->assertForbiddenService(fn () => app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $this->meal->id));
    }

    public function test_claimed_override_cannot_be_removed_and_unclaim_preserves_the_grant_and_origin(): void
    {
        $id = $this->give()->json('assignment_id');
        $this->remove($id)->assertConflict()->assertJsonPath('status', 'still_used');
        $origin = MealAssignment::findOrFail($id)->override_given_at;
        $this->unclaim($id)->assertOk();
        $this->detail()->assertJsonPath('data.0.used', false)->assertJsonPath('data.0.override_at', '13:10')
            ->assertJsonPath('counts.0.left', 1)->assertJsonPath('counts.0.overrides', 0);
        $this->assertTrue($origin->equalTo(MealAssignment::findOrFail($id)->override_given_at));
        $this->give()->assertConflict()->assertJsonPath('status', 'unused_meal');
        $this->remove($id)->assertOk()->assertJsonPath('status', 'override_removed');
        $this->assertDatabaseMissing('meal_assignments', ['id' => $id]);
        $this->detail()->assertJsonPath('recordsTotal', 0)->assertJsonPath('counts', []);
        $this->remove($id)->assertUnprocessable()->assertJsonValidationErrors('assignment_id');
        $this->assertSame('already_removed', app(MealOverrideService::class)->remove($this->event, $this->member, $this->user, $id)['status']);
    }

    public function test_unclaimed_override_can_be_claimed_again_and_stale_unclaim_cannot_clear_it(): void
    {
        $id = $this->give()->json('assignment_id');
        $oldToken = MealAssignment::findOrFail($id)->claim_token;
        $this->unclaim($id)->assertOk();
        $this->postJson(route('meals.claims.store', [$this->event, $this->member]), ['assignment_id' => $id])->assertCreated();
        $this->deleteJson(route('meals.claims.destroy', [$this->event, $this->member]), ['assignment_id' => $id, 'claim_token' => $oldToken])->assertConflict();
        $this->remove($id)->assertConflict();
        $this->assertTrue(MealAssignment::findOrFail($id)->is_override);
    }

    public function test_override_used_meals_participate_in_normal_same_type_warnings(): void
    {
        $this->give()->assertCreated();
        $normal = app(MealAssignmentService::class)->assign($this->event, $this->member, $this->meal);
        $this->postJson(route('meals.claims.store', [$this->event, $this->member]), ['assignment_id' => $normal->id])->assertConflict()->assertJsonPath('status', 'warning_required');
        $this->postJson(route('meals.claims.store', [$this->event, $this->member]), ['assignment_id' => $normal->id, 'confirm_warning' => true])->assertCreated();
        $this->assertFalse($normal->fresh()->is_override);
        $this->assertNotNull($normal->fresh()->warning_overridden_at);
        $this->detail()->assertJsonPath('counts.0.overrides', 1)->assertJsonPath('counts.0.total', 2);
    }

    public function test_requests_refuse_invalid_and_foreign_meals_members_events_and_non_override_records(): void
    {
        $foreign = app(EventService::class)->create(['name' => 'Other', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $foreignMeal = $foreign->meals()->create(['name' => 'Other lunch', 'meal_type_id' => $foreign->mealTypes()->firstOrFail()->id, 'date' => '2026-10-01', 'starts_at' => '12:00', 'ends_at' => '14:00']);
        foreach ([[], ['meal_id' => 'bad', 'confirmed' => true], ['meal_id' => $foreignMeal->id, 'confirmed' => true], ['meal_id' => $this->meal->id, 'confirmed' => false]] as $payload) {
            $this->postJson(route('meals.overrides.store', [$this->event, $this->member]), $payload)->assertUnprocessable();
        }
        $this->postJson(route('meals.overrides.store', [$foreign, $this->member]), ['meal_id' => $foreignMeal->id, 'confirmed' => true])->assertNotFound();
        $other = $this->member();
        $id = $this->give()->json('assignment_id');
        $this->deleteJson(route('meals.overrides.destroy', [$this->event, $other]), ['assignment_id' => $id, 'confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('assignment_id');
        $normal = app(MealAssignmentService::class)->assign($this->event, $other, $this->meal);
        $this->deleteJson(route('meals.overrides.destroy', [$this->event, $other]), ['assignment_id' => $normal->id, 'confirmed' => true])->assertUnprocessable();
        $this->remove($id, false)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->member->update(['status' => 'applied']);
        $this->give()->assertNotFound();
        $this->mealOptions()->assertNotFound();
        $this->remove($id)->assertNotFound();
        $this->member->update(['status' => 'hired', 'event_id' => $foreign->id]);
        $this->give()->assertNotFound();
        try {
            app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $this->meal->id);
            $this->fail('Foreign members must be refused by the service.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_locked_event_blocks_giving_removing_and_unclaiming_including_admins_and_stale_services(): void
    {
        $id = $this->give()->json('assignment_id');
        $this->unclaim($id);
        Event::findOrFail($this->event->id)->lock();
        $this->give()->assertForbidden();
        $this->remove($id)->assertForbidden();
        $this->unclaim($id)->assertForbidden();
        $this->detail()->assertJsonPath('can_override', false)->assertJsonPath('can_remove_override', false);
        $this->assertForbiddenService(fn () => app(MealOverrideService::class)->give($this->event, $this->member, $this->user, $this->meal->id));
        $this->assertForbiddenService(fn () => app(MealOverrideService::class)->remove($this->event, $this->member, $this->user, $id));
    }

    public function test_event_timezone_and_inclusive_overnight_window_apply_to_options_giving_and_removal(): void
    {
        $this->meal->update(['starts_at' => '23:30:00', 'ends_at' => '00:30:00']);
        $this->travelTo(Carbon::parse('2026-10-02 00:30:00', $this->event->timezone));
        $this->mealOptions()->assertJsonPath('data.0.date', '2026-10-01');
        $id = $this->give()->assertCreated()->json('assignment_id');
        $this->unclaim($id)->assertOk();
        $this->remove($id)->assertOk();
        $id = $this->give()->assertCreated()->json('assignment_id');
        $this->unclaim($id)->assertOk();
        $this->travelTo(Carbon::parse('2026-10-02 00:30:01', $this->event->timezone));
        $this->mealOptions()->assertJsonCount(0, 'data');
        $this->give()->assertUnprocessable()->assertJsonValidationErrors('meal_id');
        $this->remove($id)->assertUnprocessable()->assertJsonValidationErrors('assignment_id');
    }

    public function test_claimed_override_protects_meal_and_member_deletion_and_keeps_meal_snapshot(): void
    {
        $id = $this->give()->json('assignment_id');
        $this->delete(route('meals.destroy', [$this->event, $this->meal]))->assertSessionHasErrors('meal');
        foreach ([fn () => app(MealService::class)->destroy($this->event, $this->meal), fn () => $this->member->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Claimed history must block deletion.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        app(MealService::class)->update($this->event, $this->meal, ['name' => 'Changed lunch', 'meal_type_id' => $this->meal->meal_type_id, 'date' => '2026-10-03', 'starts_at' => '13:00', 'ends_at' => '15:00']);
        $this->assertSame('Friday lunch', MealAssignment::findOrFail($id)->meal_name);
    }

    public function test_rollback_refuses_to_erase_override_identity(): void
    {
        $this->give()->assertCreated();
        $migration = require database_path('migrations/2026_10_07_000004_add_meal_override_origin.php');
        try {
            $migration->down();
            $this->fail('Override rollback must refuse data loss.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasColumn('meal_assignments', 'is_override'));
        }
    }

    private function member(): TeamEngagement
    {
        $person = Person::create(['name' => 'Walk-up', 'email' => fake()->unique()->safeEmail()]);

        return $this->event->teamEngagements()->create(['person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
    }

    private function meal(string $name, string $date = '2026-10-01'): Meal
    {
        return app(MealService::class)->create($this->event, ['name' => $name, 'meal_type_id' => $this->event->mealTypes()->where('name', 'Lunch')->sole()->id, 'date' => $date, 'starts_at' => '12:00', 'ends_at' => '14:00']);
    }

    private function give(): TestResponse
    {
        return $this->postJson(route('meals.overrides.store', [$this->event, $this->member]), ['meal_id' => $this->meal->id, 'confirmed' => true]);
    }

    private function remove(int $id, bool $confirmed = true): TestResponse
    {
        return $this->deleteJson(route('meals.overrides.destroy', [$this->event, $this->member]), ['assignment_id' => $id, 'confirmed' => $confirmed]);
    }

    private function unclaim(int $id): TestResponse
    {
        return $this->deleteJson(route('meals.claims.destroy', [$this->event, $this->member]), ['assignment_id' => $id, 'claim_token' => MealAssignment::findOrFail($id)->claim_token]);
    }

    private function mealOptions(): TestResponse
    {
        return $this->getJson(route('meals.overrides.index', [$this->event, $this->member]));
    }

    private function detail(): TestResponse
    {
        return $this->getJson(route('meals.people.show', [$this->member, 'draw' => 1, 'start' => 0, 'length' => 25]));
    }

    private function assertForbiddenService(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The service must recheck writability and permission.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
