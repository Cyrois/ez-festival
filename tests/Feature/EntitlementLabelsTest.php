<?php

namespace Tests\Feature;

use App\Http\Resources\CheckInEntitlementResource;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\User;
use App\Queries\CheckInEntitlementsQuery;
use App\Queries\InventoryQuery;
use App\Services\EntitlementItemService;
use App\Services\InventoryMovementService;
use App\Support\EntitlementLabels;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntitlementLabelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $actor;

    private EntitlementItem $item;

    private array $labels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->event('Festival');
        $this->actor = $this->grantAdminAccess(User::factory()->create());
        $this->actor->setCurrentEvent($this->event);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->actor);
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $this->labels = [];
        foreach (['Zone 10', 'é', 'b', 'Café', 'cafe', 'A', 'Zone 2'] as $name) {
            $label = $this->event->entitlementItemLabels()->create(['name' => $name, 'color' => 'violet']);
            $this->labels[$name] = $label;
            $this->item->labels()->attach($label);
        }
    }

    public function test_every_item_payload_uses_one_natural_accent_insensitive_order(): void
    {
        $expected = ['A', 'b', 'Café', 'cafe', 'é', 'Zone 2', 'Zone 10'];
        $this->get(route('credentials.entitlements'))->assertInertia(fn (Assert $p) => $p
            ->where('items.0.labels', fn ($labels) => collect($labels)->pluck('name')->all() === $expected));
        $this->get(route('credentials.entitlements.show', $this->item))->assertInertia(fn (Assert $p) => $p
            ->where('item.labels', fn ($labels) => collect($labels)->pluck('name')->all() === $expected));
        $this->get(route('credentials.passes.create'))->assertInertia(fn (Assert $p) => $p
            ->where('entitlementItems.0.labels', fn ($labels) => collect($labels)->pluck('name')->all() === $expected));
        [$member, $pass, $assignment] = $this->assigned();
        $pass->entitlements()->create(['entitlement_item_id' => $this->item->id, 'sort_order' => 0]);
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $this->item->id]);
        $this->get(route('team.members.show', $member))->assertInertia(fn (Assert $p) => $p
            ->where('passes.0.entitlements.0.labels', fn ($labels) => collect($labels)->pluck('name')->all() === $expected));
        $this->get(route('credentials.passes.edit', $pass))->assertInertia(fn (Assert $p) => $p
            ->where('entitlementItems.0.labels', fn ($labels) => collect($labels)->pluck('name')->all() === $expected));
        $empty = $this->event->entitlementItems()->create(['name' => 'No labels']);
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $empty->id]);
        $response = $this->getJson($this->endpoint($member))->assertOk();
        $response->assertJsonPath('data.0.labels', []);
        $this->assertSame($expected, array_column($response->json('data.1.labels'), 'name'));
        $this->assertSame('violet', $response->json('data.1.labels.0.color'));
    }

    public function test_stock_holdings_picker_and_history_read_current_labels_and_preserve_counts(): void
    {
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $this->seedStock($this->item, $location, 10, $this->actor);
        app(InventoryMovementService::class)->adjustInventory($this->event, $this->actor, [
            'direction' => 'from_location', 'location_id' => $location->id, 'person_id' => $this->actor->person_id,
            'items' => [['entitlement_item_id' => $this->item->id, 'quantity' => 3]],
        ]);
        $holder = StockHolder::where('person_id', $this->actor->person_id)->sole();
        $ordered = EntitlementLabels::ordered($this->item->labels);
        $this->get(route('check-in.my-stock'))->assertInertia(fn (Assert $p) => $p
            ->where('ownItems.0.labels', $ordered)->where('items.0.labels', $ordered));
        $this->getJson(route('check-in.inventory.stock'))->assertJsonPath('data.0.balance', 3)->assertJsonPath('data.0.labels', $ordered);
        $this->getJson(route('check-in.inventory.adjustment-stock', ['direction' => 'to_location', 'person_id' => $this->actor->person_id]))->assertJsonPath('data.0.labels', $ordered);
        $this->get(route('check-in.inventory.index'))->assertInertia(fn (Assert $p) => $p->where('items.0.labels', $ordered));
        $this->labels['A']->update(['name' => 'Zebra', 'color' => 'rose']);
        $this->labels['b']->delete();
        $ordered = EntitlementLabels::ordered($this->item->fresh()->labels);
        foreach (['check-in.inventory.movements' => [], 'check-in.history.data' => ['holder' => 'person:'.$this->actor->person_id]] as $route => $params) {
            $this->getJson(route($route, $params))->assertOk()->assertJsonPath('data.0.labels', $ordered)->assertJsonPath('data.0.quantity', 3);
        }
        $this->event->lock();
        $this->getJson(route('check-in.inventory.stock'))->assertOk()->assertJsonPath('data.0.labels', $ordered);
        $viewer = $this->grantRoleAccess(User::factory()->create(), ['checkin.view']);
        $this->actingAs($viewer)->getJson(route('check-in.inventory.holdings'))->assertForbidden();
        $this->getJson(route('check-in.history.data'))->assertForbidden();
    }

    public function test_label_mutations_invalidate_the_cached_list_and_foreign_labels_are_not_loaded(): void
    {
        $items = app(EntitlementItemService::class);
        $items->list($this->event);
        $this->labels['A']->update(['name' => 'Updated', 'color' => 'rose']);
        $this->assertSame('rose', $items->list($this->event)->first()->labels->firstWhere('name', 'Updated')->color);
        $this->labels['b']->delete();
        $this->assertCount(6, $items->list($this->event)->first()->labels);
        $other = $this->event('Foreign');
        $foreign = $other->entitlementItems()->create(['name' => 'Foreign item']);
        $foreign->labels()->attach($other->entitlementItemLabels()->create(['name' => 'Secret', 'color' => 'danger']));
        $this->assertSame([], EntitlementLabels::forItems($this->event->id, [$foreign->id])->all());
        [$member, , $assignment] = $this->assigned();
        $assignment->expectedEntitlements()->create(['entitlement_item_id' => $foreign->id]);
        $this->getJson($this->endpoint($member))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_label_query_count_stays_flat_as_check_in_and_stock_rows_grow(): void
    {
        [$member, , $assignment] = $this->assigned();
        $location = $this->event->locations()->create(['name' => 'Gate']);
        $make = function (int $count) use ($assignment, $location): void {
            for ($i = 0; $i < $count; $i++) {
                $item = $this->event->entitlementItems()->create(['name' => 'Item '.($assignment->expectedEntitlements()->count() + 1)]);
                $item->labels()->attach($this->labels['A']);
                $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
                $this->seedStock($item, $location, 5, $this->actor);
            }
        };
        $measure = function () use ($member, $location): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = app(CheckInEntitlementsQuery::class)->dataTable($this->event, $this->filters($member), $this->actor);
            CheckInEntitlementResource::collection($result['rows'])->resolve();
            $checkIn = count(DB::getQueryLog());
            DB::flushQueryLog();
            $holder = StockHolder::where('location_id', $location->id)->sole();
            $query = app(InventoryQuery::class);
            $query->itemRows($this->event->id, $query->items($this->event->id, $holder->id)->get());
            $stock = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$checkIn, $stock];
        };
        $make(2);
        $small = $measure();
        $make(18);
        $this->assertSame($small, $measure());
    }

    private function event(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
    }

    private function assigned(): array
    {
        $person = Person::create(['name' => 'Guest', 'email' => 'guest@example.test']);
        $member = $this->createTeamEngagement(['event_id' => $this->event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $pass = $this->event->passTypes()->create(['name' => 'Crew']);
        $assignment = $member->passAssignments()->create(['person_id' => $person->id, 'pass_type_id' => $pass->id]);

        return [$member, $pass, $assignment];
    }

    private function filters($member): array
    {
        return ['type' => 'team', 'engagement_id' => $member->id, 'person_id' => $member->person_id, 'draw' => 1, 'start' => 0, 'length' => -1];
    }

    private function endpoint($member): string
    {
        return route('check-in.entitlements', $this->filters($member));
    }
}
