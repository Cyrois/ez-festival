<?php

namespace Tests\Feature;

use App\Enums\StockMovement as StockMovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\User;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Services\EventLocationService;
use App\Services\EventService;
use App\Services\StockMovementService;
use App\Services\TeamEngagementService;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StockMovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_movement_types_derive_balances_and_record_the_actor_separately(): void
    {
        [$event, $actor, $item, $location, $person] = $this->context();
        $stocks = app(StockMovementService::class);
        $recipient = $stocks->forPerson($event, Person::create(['name' => 'Recipient', 'email' => 'recipient@example.test']));
        $stocks->move($item, StockMovementType::Add, 10, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 4, $location, $person, $actor);
        $stocks->move($item, StockMovementType::Transfer, 2, $person, $recipient, $actor);
        $stocks->move($item, StockMovementType::Return, 1, $recipient, $location, $actor);
        $takeBack = $stocks->move($item, StockMovementType::TakeBack, 1, $recipient, $person, $actor, '  Leftovers  ');
        $stocks->move($item, StockMovementType::Return, 1, $person, $location, $actor);
        $this->assertSame(10, $stocks->total($item));
        $stocks->move($item, StockMovementType::Remove, 2, $location, null, $actor, 'Damaged');
        $issue = $this->expected($event, $item)->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'location_id' => $location->location_id]);
        $stocks->move($item, StockMovementType::GiveOut, 1, $location, null, $actor, issued: $issue);

        $this->assertSame(5, $stocks->balance($item, $location));
        $this->assertSame(2, $stocks->balance($item, $person));
        $this->assertSame(0, $stocks->balance($item, $recipient));
        $this->assertSame(7, $stocks->total($item));
        $this->assertSame('Leftovers', $takeBack->note);
        $this->assertSame($recipient->id, $takeBack->from_holder_id);
        $this->assertSame($actor->id, $takeBack->actor_user_id);
        $this->assertEqualsCanonicalizing(StockMovementType::cases(), $item->movements()->distinct()->pluck('type')->all());
        $this->assertFalse(Schema::hasTable('entitlement_adjustments'));
    }

    public function test_admin_joining_team_keeps_the_same_person_holder_and_exact_history(): void
    {
        [$event, $actor, $item, $location, $holder] = $this->context();
        $stocks = app(StockMovementService::class);
        $stocks->move($item, StockMovementType::Add, 50, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 50, $location, $holder, $actor);
        $history = $item->movements()->orderBy('id')->get()->toArray();
        $this->assertFalse($actor->person->teamEngagements()->where('event_id', $event->id)->exists());

        app(TeamEngagementService::class)->create($event, [
            'name' => $actor->person->name, 'email' => $actor->person->email,
            'status' => 'hired', 'employment_type' => 'volunteer',
        ]);

        $this->assertSame($holder->id, $stocks->forPerson($event, $actor->person)->id);
        $this->assertSame(50, $stocks->balance($item, $holder));
        $this->assertSame($history, $item->movements()->orderBy('id')->get()->toArray());
        $this->assertSame(1, StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)->count());
    }

    public function test_same_person_has_distinct_event_holders_and_event_delete_preserves_other_stock(): void
    {
        [$event, $actor, $item, $location, $holder] = $this->context();
        [$otherEvent, , $otherItem, $otherLocation] = $this->context('Other event');
        $stocks = app(StockMovementService::class);
        $otherHolder = $stocks->forPerson($otherEvent, $actor->person);
        $stocks->move($item, StockMovementType::Add, 5, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 5, $location, $holder, $actor);
        $stocks->move($otherItem, StockMovementType::Add, 8, null, $otherLocation, $actor);
        $stocks->move($otherItem, StockMovementType::Pull, 3, $otherLocation, $otherHolder, $actor);
        $this->assertNotSame($holder->id, $otherHolder->id);

        app(EventService::class)->delete($event);

        $this->assertDatabaseMissing('stock_holders', ['event_id' => $event->id]);
        $this->assertDatabaseMissing('stock_movements', ['event_id' => $event->id]);
        $this->assertModelExists($actor->person);
        $this->assertSame(3, $stocks->balance($otherItem, $otherHolder));
        $this->assertSame(8, $stocks->total($otherItem));
    }

    public function test_location_and_item_pages_derive_totals_including_stock_held_by_people(): void
    {
        [$event, $actor, $item, $location, $holder] = $this->context();
        $stocks = app(StockMovementService::class);
        $stocks->move($item, StockMovementType::Add, 10, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 6, $location, $holder, $actor);
        $this->actingAs($actor)->get(route('credentials.entitlements'))->assertInertia(fn (Assert $page) => $page->where('items.0.balance', 10));
        $this->get(route('credentials.entitlements.edit', $item))->assertInertia(fn (Assert $page) => $page
            ->where('stats.in_stock', 10)->where('locations.0.in_stock', 4)->has('locations', 1));
        $stocks->move($item, StockMovementType::Remove, 1, $location, null, $actor, 'Counted wrong');
        $this->get(route('credentials.entitlements'))->assertInertia(fn (Assert $page) => $page->where('items.0.balance', 9));
    }

    public function test_removal_requires_a_reason_through_form_request_and_service(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $this->seedStock($item, $location->location, 10, $actor);
        foreach ([null, '', '   '] as $reason) {
            $this->actingAs($actor)->postJson(route('credentials.entitlements.adjustments.store', [$event, $item]), [
                'location_id' => $location->location_id, 'direction' => 'remove', 'quantity' => 1, 'reason' => $reason,
            ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        try {
            app(StockMovementService::class)->move($item, StockMovementType::Remove, 1, $location, null, $actor);
            $this->fail('Removal without a reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame(10, app(StockMovementService::class)->total($item));
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_insufficient_stock_rolls_back_issue_and_expected_status(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $expected = $this->expected($event, $item);
        try {
            app(EntitlementConsumeService::class)->consume($expected, $actor, $location->location_id);
            $this->fail('Empty stock must refuse consume.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame(ExpectedEntitlement::STATUS_EXPECTED, $expected->fresh()->status);
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_consumption_has_one_issue_link_and_duplicate_link_is_a_controlled_conflict(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $this->seedStock($item, $location->location, 2, $actor);
        $expected = $this->expected($event, $item);
        $issued = app(EntitlementConsumeService::class)->consume($expected, $actor, $location->location_id);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::GiveOut, 'quantity' => 1,
            'from_holder_id' => $location->id, 'to_holder_id' => null, 'issued_entitlement_id' => $issued->id, 'actor_user_id' => $actor->id]);
        try {
            app(StockMovementService::class)->move($item, StockMovementType::GiveOut, 1, $location, null, $actor, issued: $issued);
            $this->fail('An issue must debit stock only once.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_entitlement', $exception->errors());
        }
        $this->assertSame(1, app(StockMovementService::class)->total($item));
    }

    public function test_item_delete_is_blocked_by_person_stock_and_allowed_after_stock_is_returned_and_removed(): void
    {
        [$event, $actor, $item, $location, $person] = $this->context();
        $stocks = app(StockMovementService::class);
        $stocks->move($item, StockMovementType::Add, 5, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 5, $location, $person, $actor);
        $this->actingAs($actor)->deleteJson(route('credentials.entitlements.destroy', [$event, $item]))
            ->assertUnprocessable()->assertJsonValidationErrors('item');
        try {
            app(EntitlementItemService::class)->destroy($item);
            $this->fail('A service call must not delete person-held stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }
        $stocks->move($item, StockMovementType::Return, 5, $person, $location, $actor);
        $stocks->move($item, StockMovementType::Remove, 5, $location, null, $actor, 'Retired item');
        $this->delete(route('credentials.entitlements.destroy', [$event, $item]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($item);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_zero_balance_location_history_blocks_both_delete_forms_and_service(): void
    {
        [$event, $actor, $item, $holder] = $this->context();
        $stocks = app(StockMovementService::class);
        $stocks->move($item, StockMovementType::Add, 1, null, $holder, $actor);
        $stocks->move($item, StockMovementType::Remove, 1, $holder, null, $actor, 'Damaged');
        $location = $holder->location;
        $this->actingAs($actor)->deleteJson(route('settings.events.locations.destroy', [$event, $location]))
            ->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->deleteJson(route('setup.locations.destroy', $location))->assertUnprocessable()->assertJsonValidationErrors('location');
        try {
            app(EventLocationService::class)->destroy($event, $location);
            $this->fail('Stock history must block service deletion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('location', $exception->errors());
        }
        $this->assertModelExists($location);
    }

    public function test_unused_holder_does_not_block_location_delete(): void
    {
        [$event, , , $holder] = $this->context();
        app(EventLocationService::class)->destroy($event, $holder->location);
        $this->assertModelMissing($holder);
    }

    public function test_locked_event_refuses_movements_and_holder_creation_even_with_stale_models(): void
    {
        [$event, $actor, $item, $holder] = $this->context();
        $event->lock();
        foreach ([fn () => app(StockMovementService::class)->move($item, StockMovementType::Add, 1, null, $holder, $actor),
            fn () => app(StockMovementService::class)->forPerson($event, $actor->person),
            fn () => app(StockMovementService::class)->forLocation($event, $holder->location_id)] as $write) {
            try {
                $write();
                $this->fail('Locked events must refuse every stock write.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_foreign_event_holder_is_refused_in_service_and_database(): void
    {
        [$event, $actor, $item] = $this->context();
        [, , , $foreign] = $this->context('Foreign event');
        try {
            app(StockMovementService::class)->move($item, StockMovementType::Add, 1, null, $foreign, $actor);
            $this->fail('Foreign holders must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('holder', $exception->errors());
        }
        $this->assertDatabaseRejected(fn () => $item->movements()->create([
            'event_id' => $event->id, 'quantity' => 1, 'type' => StockMovementType::Add, 'to_holder_id' => $foreign->id,
        ]));
    }

    public function test_holder_target_uniqueness_event_scope_and_immutable_identity(): void
    {
        [$event, $actor, , $location, $person] = $this->context();
        [, , , $foreign] = $this->context('Foreign event');
        foreach ([['event_id' => $event->id],
            ['event_id' => $event->id, 'person_id' => $actor->person_id, 'location_id' => $location->location_id],
            ['event_id' => $event->id, 'location_id' => $location->location_id],
            ['event_id' => $event->id, 'person_id' => $actor->person_id],
            ['event_id' => $event->id, 'location_id' => $foreign->location_id]] as $data) {
            $this->assertDatabaseRejected(fn () => StockHolder::create($data));
        }
        $this->assertDatabaseRejected(fn () => $person->update(['person_id' => null, 'location_id' => $location->location_id]));
        $this->assertSame($actor->person_id, $person->fresh()->person_id);
    }

    #[DataProvider('invalidMovements')]
    public function test_database_rejects_invalid_movement_shapes(string $type, ?string $from, ?string $to, int|float $quantity, ?string $note, bool $withIssue): void
    {
        [$event, , $item, $location, $person] = $this->context();
        $holders = ['location' => $location, 'person' => $person];
        $issue = $this->expected($event, $item)->issuedEntitlement()->create(['entitlement_item_id' => $item->id]);
        $this->assertDatabaseRejected(fn () => DB::table('stock_movements')->insert([
            'entitlement_item_id' => $item->id, 'event_id' => $event->id, 'quantity' => $quantity, 'type' => $type,
            'from_holder_id' => $from === null ? null : $holders[$from]->id,
            'to_holder_id' => $to === null ? null : $holders[$to]->id,
            'note' => $note, 'issued_entitlement_id' => $withIssue ? $issue->id : null,
        ]));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public static function invalidMovements(): array
    {
        $cases = [
            'zero' => ['add', null, 'location', 0, null, false],
            'negative' => ['add', null, 'location', -1, null, false],
            'fraction' => ['add', null, 'location', 0.5, null, false],
            'overflow' => ['add', null, 'location', 2147483648, null, false],
            'unknown type' => ['other', null, 'location', 1, null, false],
            'add with source' => ['add', 'person', 'location', 1, null, false],
            'add without target' => ['add', null, null, 1, null, false],
            'add to person' => ['add', null, 'person', 1, null, false],
            'remove from person' => ['remove', 'person', null, 1, 'Damaged', false],
            'remove without reason' => ['remove', 'location', null, 1, null, false],
            'remove blank reason' => ['remove', 'location', null, 1, '   ', false],
            'remove with target' => ['remove', 'location', 'person', 1, 'Damaged', false],
            'give out without issue' => ['give_out', 'location', null, 1, null, false],
            'give out without source' => ['give_out', null, null, 1, null, true],
            'give out with target' => ['give_out', 'location', 'person', 1, null, true],
            'give out multiple units' => ['give_out', 'location', null, 2, null, true],
            'issue on add' => ['add', null, 'location', 1, null, true],
        ];
        foreach (['pull', 'return', 'transfer', 'take_back'] as $type) {
            $cases[$type.' same holder'] = [$type, 'location', 'location', 1, null, false];
            $cases[$type.' missing source'] = [$type, null, 'person', 1, null, false];
            $cases[$type.' missing target'] = [$type, 'person', null, 1, null, false];
        }

        return $cases;
    }

    public function test_issue_item_must_match_and_issue_link_is_unique_and_restricted(): void
    {
        [$event, $actor, $item, $holder] = $this->context();
        $otherItem = $event->entitlementItems()->create(['name' => 'Other item']);
        $issue = $this->expected($event, $otherItem)->issuedEntitlement()->create(['entitlement_item_id' => $otherItem->id]);
        $this->assertDatabaseRejected(fn () => $item->movements()->create(['event_id' => $event->id,
            'type' => StockMovementType::GiveOut, 'quantity' => 1, 'from_holder_id' => $holder->id, 'issued_entitlement_id' => $issue->id]));
        $this->seedStock($item, $holder->location, 2, $actor);
        $issue = app(EntitlementConsumeService::class)->consume($this->expected($event, $item), $actor, $holder->location_id);
        $movement = $item->movements()->where('type', StockMovementType::GiveOut)->sole();
        $this->assertSame(StockMovementType::GiveOut, $movement->type);
        $this->assertSame('give_out', $movement->getRawOriginal('type'));
        $this->assertSame('give_out', $movement->toArray()['type']);
        $this->assertDatabaseRejected(fn () => $item->movements()->create($movement->only('event_id', 'type', 'quantity', 'from_holder_id', 'issued_entitlement_id')));
        $this->assertDatabaseRejected(fn () => $issue->delete());
        $this->assertDatabaseRejected(fn () => $issue->update(['entitlement_item_id' => $otherItem->id]));
    }

    public function test_movements_are_immutable_but_actor_deletion_preserves_history_and_balance(): void
    {
        [$event, $actor, $item, $holder] = $this->context();
        $move = app(StockMovementService::class)->move($item, StockMovementType::Add, 2, null, $holder, $actor);
        $this->assertDatabaseRejected(fn () => $move->update(['quantity' => 3]));
        $actor->delete();
        $this->assertNull($move->fresh()->actor_user_id);
        $this->assertSame(2, app(StockMovementService::class)->total($item));
    }

    private function assertDatabaseRejected(callable $write): void
    {
        try {
            // PostgreSQL remains usable after the rejected statement's savepoint rolls back.
            DB::transaction($write);
            $this->fail('Expected the database to reject this write.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

    private function context(string $name = 'Inventory event'): array
    {
        $event = Event::create(['name' => $name, 'starts_on' => '2026-07-10', 'ends_on' => '2026-07-12', 'timezone' => 'America/Vancouver']);
        $actor = User::factory()->create();
        $this->grantAdminAccess($actor);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $actor->setCurrentEvent($event);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $stocks = app(StockMovementService::class);
        $location = $stocks->forLocation($event, $event->locations()->create(['name' => 'Gate'])->id);
        $person = $stocks->forPerson($event, $actor->person);

        return [$event, $actor, $item, $location, $person];
    }

    private function expected(Event $event, EntitlementItem $item): ExpectedEntitlement
    {
        $pass = $event->passTypes()->firstOrCreate(['name' => 'Pass '.$item->id]);
        $patron = $event->patrons()->firstOrCreate(['person_id' => User::query()->value('person_id')]);
        $assignment = $pass->assignments()->create(['event_patron_id' => $patron->id]);

        return $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
    }
}
