<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\TeamForm;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Services\EventLocationService;
use App\Services\EventService;
use App\Services\PassAssignmentService;
use App\Services\PassTypeService;
use App\Services\TeamFormService;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DatabaseAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[DataProvider('contactDomains')]
    public function test_code_search_preserves_totals_across_assignments_and_status_filters(string $domain): void
    {
        [$user, $event] = $this->context();
        $engagement = $domain === 'artist'
            ? ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed'])
            : VendorEngagement::create(['vendor_id' => Vendor::create(['name' => 'Check-in Vendor'])->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        $person = Person::create(['name' => 'Partially Checked In', 'email' => 'partial@example.test']);
        $engagement->people()->attach($person);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $otherPass = $event->passTypes()->create(['name' => 'Guest']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $location = $event->locations()->create(['name' => 'Gate']);

        foreach ([$pass, $pass, $otherPass] as $index => $type) {
            $assignment = $engagement->passAssignments()->create(['pass_type_id' => $type->id, 'person_id' => $person->id]);
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
            if ($index === 0) {
                $expected->update(['status' => 'consumed']);
                $expected->issuedEntitlement()->create([
                    'entitlement_item_id' => $item->id, 'location_id' => $location->id,
                    'code' => 'ISSUED-UNIQUE', 'issued_by' => $user->id, 'issued_at' => now(),
                ]);
            }
        }

        $this->actingAs($user)->get(route('check-in.index', ['search' => 'ISSUED-UNIQUE', 'status' => 'partial']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('people.data', 1)
            ->where('people.data.0.expected', 3)->where('people.data.0.issued', 1)
            ->where('people.data.0.check_in_status', 'partial'));
        $this->get(route('check-in.index', ['search' => 'ISSUED-UNIQUE', 'status' => 'complete']))
            ->assertInertia(fn (Assert $page) => $page->has('people.data', 0));
        $this->get(route('check-in.index', ['search' => 'ISSUED-UNIQUE', 'pass' => $pass->id]))
            ->assertInertia(fn (Assert $page) => $page->where('people.data.0.expected', 2)
                ->where('people.data.0.issued', 1)->where('people.data.0.pass_name', 'Artist'));
        $this->get(route('check-in.index', ['search' => 'ISSUED-UNIQUE', 'pass' => $otherPass->id]))
            ->assertInertia(fn (Assert $page) => $page->has('people.data', 0));
    }

    public function test_vendor_save_preserves_empty_snapshots_but_new_assignments_use_current_lines(): void
    {
        [$user, $event] = $this->context();
        $vendor = Vendor::create(['name' => 'Snapshot Vendor']);
        $engagement = VendorEngagement::create(['vendor_id' => $vendor->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        $pass = $event->passTypes()->create(['name' => 'Originally Empty']);
        app(PassAssignmentService::class)->give($engagement, $pass, 1);
        $assignment = $engagement->passAssignments()->firstOrFail();
        $item = $event->entitlementItems()->create(['name' => 'Added Later']);
        app(PassTypeService::class)->update($pass, ['name' => $pass->name, 'entitlement_item_ids' => [$item->id]], new Collection);

        $this->actingAs($user)->put(route('vendors.update', $engagement), [
            'name' => $vendor->name, 'status' => 'confirmed',
            'pass_assignments' => [
                ['id' => $assignment->id, 'pass_type_id' => $pass->id, 'person_id' => null],
                ['pass_type_id' => $pass->id, 'person_id' => null],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $assignment->expectedEntitlements()->count());
        $newAssignment = $engagement->passAssignments()->whereKeyNot($assignment->id)->firstOrFail();
        $this->assertSame([$item->id], $newAssignment->expectedEntitlements()->pluck('entitlement_item_id')->all());
    }

    public static function contactDomains(): array
    {
        return [['artist'], ['vendor']];
    }

    #[DataProvider('contactDomains')]
    public function test_repeated_contact_submissions_preserve_one_link_and_primary_flags(string $domain): void
    {
        [$user, $event] = $this->context();
        $engagement = $domain === 'artist'
            ? ArtistEngagement::factory()->for($event)->create()
            : VendorEngagement::create(['event_id' => $event->id, 'vendor_id' => Vendor::create(['name' => 'Vendor'])->id, 'status' => 'idea']);
        $route = route($domain === 'artist' ? 'artists.people.store' : 'vendors.people.store', $engagement);
        $first = ['name' => 'Primary', 'email' => 'primary@example.test', 'phone' => '555-0101'];
        $second = ['name' => 'Secondary', 'email' => 'secondary@example.test'];
        // Reusing an existing Person must not make this second link primary.
        Person::create($second);
        $this->actingAs($user)->post($route, $first)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($route, $second)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($route, [...$first, 'name' => 'Retried payload'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($route, $second)->assertRedirect()->assertSessionHasNoErrors();

        $people = $engagement->people()->orderBy('people.email')->get();
        $this->assertCount(2, $people);
        $this->assertTrue((bool) $people[0]->pivot->is_primary);
        $this->assertFalse((bool) $people[1]->pivot->is_primary);
        $this->assertSame('Primary', $people[0]->name);
        $this->assertSame('555-0101', $people[0]->phone);
    }

    public function test_stale_event_update_rechecks_the_committed_lock(): void
    {
        [, $event] = $this->context();
        Event::findOrFail($event->id)->lock();
        try {
            app(EventService::class)->update($event, ['name' => 'Written After Lock']);
            $this->fail('A stale unlocked Event must not authorize the write.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('Database Regression', $event->fresh()->name);
        $this->assertTrue($event->fresh()->isLocked());
    }

    public static function locationWrites(): array
    {
        return [['create'], ['update'], ['destroy']];
    }

    #[DataProvider('locationWrites')]
    public function test_location_writes_recheck_a_stale_event_lock(string $operation): void
    {
        [, $event] = $this->context();
        $location = $event->locations()->create(['name' => 'Original']);
        Event::findOrFail($event->id)->lock();
        $service = app(EventLocationService::class);
        try {
            match ($operation) {
                'create' => $service->createMany($event, [['name' => 'New']]),
                'update' => $service->update($event, $location, ['name' => 'Changed']),
                'destroy' => $service->destroy($event, $location),
            };
            $this->fail('Location writes must recheck the committed event lock.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(['Original'], $event->locations()->pluck('name')->all());
    }

    public function test_location_services_reject_a_foreign_event_location(): void
    {
        [, $event] = $this->context();
        $other = Event::create(['name' => 'Other', 'starts_on' => '2027-07-01', 'ends_on' => '2027-07-02', 'timezone' => 'UTC']);
        $location = $other->locations()->create(['name' => 'Foreign']);
        $this->expectException(ModelNotFoundException::class);
        app(EventLocationService::class)->update($event, $location, ['name' => 'Changed']);
    }

    public function test_location_deletion_blocks_issued_rows_in_both_request_and_service(): void
    {
        [$user, $event] = $this->context();
        $location = $event->locations()->create(['name' => 'Gate']);
        $engagement = ArtistEngagement::factory()->for($event)->create();
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id, 'status' => 'consumed']);
        // A legacy/imported issued row can exist without a stock adjustment.
        $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $location->id, 'issued_by' => $user->id, 'issued_at' => now()]);
        $this->actingAs($user)->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect()->assertSessionHasErrors('location');
        try {
            app(EventLocationService::class)->destroy($event, $location);
            $this->fail('The service must recheck restrict-FK children after validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('location', $exception->errors());
        }
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertSame(1, $expected->issuedEntitlement()->count());
    }

    public function test_public_form_submit_rechecks_a_stale_event_lock(): void
    {
        [, $event] = $this->context();
        $form = TeamForm::create(['event_id' => $event->id, 'name' => 'Crew', 'slug' => 'crew', 'status' => 'live']);
        $form->load('event');
        Event::findOrFail($event->id)->lock();
        try {
            app(TeamFormService::class)->submit($form, ['name' => 'Applicant', 'email' => 'applicant@example.test']);
            $this->fail('Public submissions must recheck the event lock.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, $event->teamEngagements()->count());
        $this->assertDatabaseMissing('people', ['email' => 'applicant@example.test']);
    }

    public function test_pass_capacity_validation_matches_postgresql_integer_limits(): void
    {
        [$user, $event] = $this->context();
        $this->actingAs($user)->postJson(route('credentials.passes.store', $event), ['name' => 'Too Large', 'max_assignments' => 2147483648])
            ->assertUnprocessable()->assertJsonValidationErrors('max_assignments');
        $this->assertDatabaseMissing('pass_types', ['name' => 'Too Large']);
        $this->post(route('credentials.passes.store', $event), ['name' => 'Maximum', 'max_assignments' => 2147483647])
            ->assertRedirect()->assertSessionHasNoErrors();
        $pass = $event->passTypes()->where('name', 'Maximum')->firstOrFail();
        $this->assertSame(2147483647, $pass->max_assignments);
        $this->put(route('credentials.passes.update', [$event, $pass]), ['name' => 'Maximum', 'max_assignments' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $pass->fresh()->max_assignments);
        $this->put(route('credentials.passes.update', [$event, $pass]), ['name' => 'Maximum', 'max_assignments' => 2147483647])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2147483647, $pass->fresh()->max_assignments);
        $this->putJson(route('credentials.passes.update', [$event, $pass]), ['name' => 'Maximum', 'max_assignments' => 2147483648])
            ->assertUnprocessable()->assertJsonValidationErrors('max_assignments');
        $this->assertSame(2147483647, $pass->fresh()->max_assignments);
    }

    public function test_stock_validation_matches_postgresql_integer_limits_for_both_signs(): void
    {
        [$user, $event] = $this->context();
        $location = $event->locations()->create(['name' => 'Warehouse']);
        $payload = ['name' => 'Stock', 'location_id' => $location->id, 'opening_balance' => 2147483648];
        $this->actingAs($user)->postJson(route('credentials.entitlements.store', $event), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('opening_balance');
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Stock']);
        $this->post(route('credentials.entitlements.store', $event), [...$payload, 'opening_balance' => 2147483647])
            ->assertRedirect()->assertSessionHasNoErrors();
        $item = $event->entitlementItems()->firstOrFail();
        $this->assertSame(2147483647, $item->adjustments()->sole()->delta);
        foreach (['add', 'remove'] as $direction) {
            $this->postJson(route('credentials.entitlements.adjustments.store', [$event, $item]), [
                'location_id' => $location->id, 'direction' => $direction, 'quantity' => 2147483648,
            ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->assertSame(1, $item->adjustments()->count());
        $this->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $location->id, 'direction' => 'remove', 'quantity' => 2147483647,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(-2147483647, $item->adjustments()->orderByDesc('id')->firstOrFail()->delta);
        $this->assertSame(0, (int) $item->adjustments()->sum('delta'));
        $this->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $location->id, 'direction' => 'add', 'quantity' => 2147483647,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2147483647, $item->adjustments()->orderByDesc('id')->firstOrFail()->delta);
        $this->assertSame(3, $item->adjustments()->count());
        $this->assertSame(2147483647, (int) $item->adjustments()->sum('delta'));
    }

    public function test_team_event_queries_do_not_grow_with_the_number_of_members(): void
    {
        [$user, $event] = $this->context();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (preg_match('/from ["`]events["`]/i', $query->sql)) {
                $queries[] = $query->sql;
            }
        });
        $this->actingAs($user);
        $smallQueryCounts = [];

        foreach (range(1, 30) as $number) {
            $person = Person::create(['name' => "Member {$number}", 'email' => "member-{$number}@example.test"]);
            TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'status' => 'applied', 'employment_type' => 'volunteer']);

            if (! in_array($number, [1, 30], true)) {
                continue;
            }

            foreach (['columns', 'list'] as $view) {
                $queries = [];
                $this->get(route('team.advancement', ['view' => $view]))->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->has('engagements.data', $view === 'list' ? min($number, 25) : $number));
                $this->assertLessThanOrEqual(6, count($queries), implode("\n", $queries));

                if ($number === 1) {
                    $smallQueryCounts[$view] = count($queries);
                } else {
                    $this->assertLessThanOrEqual($smallQueryCounts[$view], count($queries), "Event queries grew in {$view} view.");
                }
            }
        }
    }

    public function test_check_in_aggregates_stock_without_hydrating_the_ledger_history(): void
    {
        [$user, $event] = $this->context();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);
        $person = Person::create(['name' => 'Holder', 'email' => 'holder@example.test']);
        $engagement->people()->attach($person);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $person->id]);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $location = $event->locations()->create(['name' => 'Available']);
        $empty = $event->locations()->create(['name' => 'Empty']);
        $item->adjustments()->createMany([
            ['location_id' => $location->id, 'delta' => 30], ['location_id' => $location->id, 'delta' => -4],
            ['location_id' => $empty->id, 'delta' => 2], ['location_id' => $empty->id, 'delta' => -2],
        ]);
        $ledgerQueries = [];
        DB::listen(function ($query) use (&$ledgerQueries): void {
            if (preg_match('/from ["`]entitlement_adjustments["`]/i', $query->sql)) {
                $ledgerQueries[] = strtolower($query->sql);
            }
        });
        $this->actingAs($user)->get(route('check-in.show', $engagement))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('engagement.people.0.entitlements.0.locations', 1)
                ->where('engagement.people.0.entitlements.0.locations.0.name', 'Available')
                ->where('engagement.people.0.entitlements.0.locations.0.in_stock', 26));
        $this->assertCount(1, $ledgerQueries);
        $this->assertStringContainsString('sum(delta)', $ledgerQueries[0]);
        $this->assertStringContainsString('group by', $ledgerQueries[0]);
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Database Regression', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
