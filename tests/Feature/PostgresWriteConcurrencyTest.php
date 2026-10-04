<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\EngagementPersonService;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Services\EventService;
use App\Services\GlobalTeamService;
use App\Services\PassAssignmentService;
use App\Services\PassTypeService;
use App\Services\TeamEngagementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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

    public function test_removing_and_editing_a_pass_serialize_on_the_event(): void
    {
        [$event, , $engagement] = $this->context();
        $pass = $event->passTypes()->create(['name' => 'Original', 'max_assignments' => 1]);
        app(PassAssignmentService::class)->give($engagement, $pass, 1);
        $assignment = $engagement->passAssignments()->sole();
        $results = $this->concurrently(
            fn () => app(PassAssignmentService::class)->remove($assignment),
            fn () => app(PassTypeService::class)->update($pass, ['name' => 'Updated', 'max_assignments' => 1], new Collection),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(0, $pass->assignments()->count());
        $this->assertSame('Updated', $pass->fresh()->name);
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Concurrent Event', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);

        return [$event, $user, $engagement];
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
