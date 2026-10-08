<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\EngagementPersonService;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Services\EventService;
use App\Services\GlobalTeamService;
use App\Services\MealClaimService;
use App\Services\MealTypeService;
use App\Services\PassAssignmentService;
use App\Services\PassTypeService;
use App\Services\TeamEngagementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PostgresWriteConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private migrateTestDatabase;
    }

    public function runDatabaseMigrations(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires PostgreSQL and pcntl for two independent connections.');
        }
        $this->assertMatchesRegularExpression('/(?:_test|_audit_[a-zA-Z0-9_]+)$/', DB::connection()->getDatabaseName());
        $this->migrateTestDatabase();
    }

    public function test_giving_and_editing_a_pass_serialize_on_the_event(): void
    {
        [$event, , $engagement] = $this->context();
        $pass = $event->passTypes()->create(['name' => 'Original', 'max_assignments' => 1]);
        $results = $this->concurrently(
            fn () => app(PassAssignmentService::class)->give($engagement, $pass, 1),
            fn () => app(PassTypeService::class)->update($pass, ['name' => 'Updated', 'max_assignments' => 1], new Collection),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, $pass->assignments()->count());
        $this->assertSame('Updated', $pass->fresh()->name);
    }

    public function test_consumption_and_stock_adjustment_serialize_without_lost_inventory(): void
    {
        [$event, $user, $engagement] = $this->context();
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $item->adjustments()->create(['location_id' => $location->id, 'delta' => 2]);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user, $location->id),
            fn () => app(EntitlementItemService::class)->adjust($item, $location->id, 3, null, $user),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(4, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
        $this->assertSame(1, $expected->issuedEntitlement()->count());
    }

    public function test_duplicate_consumption_is_a_controlled_conflict(): void
    {
        [$event, $user, $engagement] = $this->context();
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $item->adjustments()->create(['location_id' => $location->id, 'delta' => 2]);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user, $location->id),
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user, $location->id),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $expected->issuedEntitlement()->count());
        $this->assertSame(1, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
    }

    public function test_settings_update_observes_an_event_lock_committed_after_model_loading(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(EventService::class)->lock($event),
            fn () => app(EventService::class)->update($event, ['name' => 'Too Late']),
        );
        $this->assertSame(['committed', 'http_403'], array_column($results, 'status'), json_encode($results));
        $this->assertTrue($event->fresh()->isLocked());
        $this->assertSame('Concurrent Event', $event->fresh()->name);
    }

    public function test_concurrent_duplicate_contacts_preserve_one_primary_link(): void
    {
        [, , $engagement] = $this->context();
        $data = ['name' => 'Contact', 'email' => 'contact@example.test'];
        $results = $this->concurrently(
            fn () => app(EngagementPersonService::class)->store($engagement, $data),
            fn () => app(EngagementPersonService::class)->store($engagement, $data),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $engagement->people()->count());
        $this->assertTrue((bool) $engagement->people()->firstOrFail()->pivot->is_primary);
    }

    public function test_global_and_event_team_updates_lock_events_before_people(): void
    {
        [$event, $actor] = $this->context();
        $this->grantAdminAccess($actor);
        $person = Person::create(['name' => 'Original', 'email' => 'member@example.test']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $results = $this->concurrently(
            fn () => app(TeamEngagementService::class)->update($member, $actor, [
                'name' => 'Original', 'email' => $person->email, 'status' => 'hired', 'employment_type' => 'volunteer',
            ]),
            fn () => app(GlobalTeamService::class)->update($person, [
                'name' => 'Global Edit', 'can_log_in' => false,
                'event_access' => [['event_id' => $event->id, 'role_id' => null]],
            ], $actor),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame('Global Edit', $person->fresh()->name);
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Concurrent Event', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);

        return [$event, $user, $engagement];
    }

    public function test_concurrent_meal_type_windows_return_a_validation_conflict(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Snack', 'starts_at' => '23:00', 'ends_at' => '01:00']),
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Night snack', 'starts_at' => '00:30', 'ends_at' => '02:00']),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, $event->mealTypes()->count());
    }

    public function test_concurrent_meal_type_names_return_a_validation_conflict(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00']),
            fn () => app(MealTypeService::class)->create($event, ['name' => 'SNACK', 'starts_at' => '04:00', 'ends_at' => '05:00']),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $event->mealTypes()->count());
    }

    public function test_simultaneous_claims_of_one_meal_record_only_one_claim(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claim = function () use ($event, $user, $member, $meal, $shift): void {
            $result = app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id);
            if ($result['status'] !== 'claimed') {
                throw ValidationException::withMessages(['meal_id' => $result['status']]);
            }
        };
        $results = $this->concurrently($claim, $claim);
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_claim_on_another_shift_observes_the_first_claim_and_requires_warning(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $other = $event->shifts()->create(['name' => 'Second shift', 'location_id' => $shift->location_id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $assignment = $other->assignments()->create(['team_engagement_id' => $member->id,
            'role_id' => $shift->assignments()->sole()->role_id, 'starts_at' => $other->starts_at, 'ends_at' => $other->ends_at]);
        $other->meals()->create(['meal_id' => $meal->id])->assignments()->attach($assignment->id);
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id),
            function () use ($event, $member, $user, $meal, $other): void {
                $result = app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $other->id);
                if ($result['status'] !== 'warning_required') {
                    throw new \RuntimeException('Expected same-type warning.');
                }
                throw ValidationException::withMessages(['meal_id' => $result['status']]);
            },
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertDatabaseCount('meal_claims', 1);
    }

    public function test_claim_observes_an_event_locked_after_models_were_loaded(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $results = $this->concurrently(
            fn () => app(EventService::class)->lock($event),
            fn () => app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id),
        );
        $this->assertSame(['committed', 'http_403'], array_column($results, 'status'), json_encode($results));
        $this->assertDatabaseCount('meal_claims', 0);
    }

    public function test_simultaneous_unclaims_correct_a_meal_only_once(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claimId = app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id)['claim_id'];
        $unclaim = function () use ($event, $user, $member, $claimId): void {
            $result = app(MealClaimService::class)->unclaim($event, $member, $user, $claimId);
            if ($result['status'] !== 'unclaimed') {
                throw ValidationException::withMessages(['claim_id' => $result['status']]);
            }
        };
        $results = $this->concurrently($unclaim, $unclaim);
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertDatabaseCount('meal_claims', 0);
    }

    public function test_claim_waits_for_an_in_progress_unclaim_and_consumes_the_restored_grant(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claimId = app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id)['claim_id'];
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->unclaim($event, $member, $user, $claimId),
            function () use ($event, $user, $member, $meal, $shift): void {
                $result = app(MealClaimService::class)->claim($event, $member, $user, $meal->id, $shift->id);
                if ($result['status'] !== 'claimed') {
                    throw new \RuntimeException('Expected the restored grant to be available.');
                }
            },
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertDatabaseCount('meal_claims', 1);
        $this->assertDatabaseMissing('meal_claims', ['id' => $claimId]);
        $this->assertSame('already_unclaimed', app(MealClaimService::class)->unclaim($event, $member, $user, $claimId)['status']);
        $this->assertDatabaseCount('meal_claims', 1);
    }

    private function mealContext(): array
    {
        $event = app(EventService::class)->create(['name' => 'Meal race', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $person = Person::create(['name' => 'Meal recipient', 'email' => 'meal@example.test']);
        $member = $event->teamEngagements()->create(['person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $location = $event->locations()->create(['name' => 'Kitchen']);
        $shift = $event->shifts()->create(['location_id' => $location->id, 'name' => 'Lunch shift', 'starts_at' => '2026-10-01 09:00', 'ends_at' => '2026-10-01 18:00']);
        $role = Role::create(['name' => 'Meal crew']);
        $assignment = $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $role->id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $meal = $event->meals()->create(['name' => 'Lunch', 'meal_type_id' => $event->mealTypes()->where('name', 'Lunch')->sole()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00:00', 'ends_at' => '14:00:00']);
        $shift->meals()->create(['meal_id' => $meal->id])->assignments()->attach($assignment->id);
        $this->travelTo(Carbon::parse('2026-10-01 12:00', 'UTC'));

        return [$event, $user, $member, $meal, $shift];
    }

    /** Run real service transactions on committed fixtures and independent connections. */
    private function concurrently(callable $first, callable $second): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'ez-db-concurrency-');
        $this->assertNotFalse($barrier);
        DB::purge();
        $pids = [];

        try {
            foreach ([$first, $second] as $index => $operation) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    $result = ['status' => 'error', 'first_lock' => null];
                    try {
                        DB::statement("SET statement_timeout = '8s'");
                        if ($index === 1) {
                            $deadline = microtime(true) + 5;
                            while (! is_file($barrier.'.first') && microtime(true) < $deadline) {
                                usleep(10000);
                                clearstatcache();
                            }
                            if (! is_file($barrier.'.first')) {
                                throw new \RuntimeException('First worker did not acquire the event lock.');
                            }
                        }
                        DB::listen(function ($query) use ($index, $barrier, &$result): void {
                            if (! str_contains(strtolower($query->sql), 'for update')) {
                                return;
                            }
                            if ($result['first_lock'] === null && preg_match('/from "([^"]+)"/', $query->sql, $matches)) {
                                $result['first_lock'] = $matches[1];
                                if ($index === 1) {
                                    touch($barrier.'.second');
                                }
                            }
                            if ($index === 0 && str_contains($query->sql, 'from "events"') && ! is_file($barrier.'.first')) {
                                touch($barrier.'.first');
                                // With correct ordering worker two waits on this event.
                                // With inverted ordering it holds its child row first.
                                $deadline = microtime(true) + 0.4;
                                while (! is_file($barrier.'.second') && microtime(true) < $deadline) {
                                    usleep(10000);
                                    clearstatcache();
                                }
                            }
                        });
                        $operation();
                        $result['status'] = 'committed';
                    } catch (ValidationException) {
                        $result['status'] = 'validation';
                    } catch (HttpException $exception) {
                        $result['status'] = 'http_'.$exception->getStatusCode();
                    } catch (\Throwable $exception) {
                        $result['error'] = get_class($exception).': '.$exception->getMessage();
                    }
                    file_put_contents($barrier.'.result.'.$index, json_encode($result, JSON_THROW_ON_ERROR));
                    DB::disconnect();
                    exit(0);
                }
                $pids[] = $pid;
            }
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0);
            }

            return array_map(fn (int $index) => json_decode(file_get_contents($barrier.'.result.'.$index), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
        } finally {
            foreach (glob($barrier.'*') as $path) {
                unlink($path);
            }
        }
    }
}
