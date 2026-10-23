<?php

namespace App\Queries;

use App\Models\Event;
use App\Services\InventoryHoldingsAccess;
use App\Services\StockMovementService;
use App\Support\SqlLike;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryHoldingsQuery
{
    public function __construct(private readonly InventoryHoldingsAccess $access, private readonly StockMovementService $stock, private readonly InventoryTargetsQuery $targets) {}

    public function people(Event $event, array $scope): Builder
    {
        return $this->access->roster($event, $scope)
            ->join('people as p', 'p.id', '=', 'member.person_id')
            ->leftJoin('stock_holders as holder', fn ($join) => $join->on('holder.person_id', '=', 'p.id')->where('holder.event_id', $event->id))
            ->select('p.id', 'p.name', 'holder.id as holder_id')
            ->selectRaw(empty($scope['shift_id']) ? 'FALSE as is_supervisor' : 'assignment.is_supervisor')
            ->orderByRaw('LOWER(p.name)')->orderBy('p.id');
    }

    public function locations(Event $event): Builder
    {
        return DB::table('locations as l')->where('l.event_id', $event->id)
            ->leftJoin('stock_holders as holder', fn ($join) => $join->on('holder.location_id', '=', 'l.id')->where('holder.event_id', $event->id))
            ->select('l.id', 'l.name', 'holder.id as holder_id')->orderByRaw('LOWER(l.name)')->orderBy('l.id');
    }

    public function adjustmentPeople(Event $event, string $direction, string $search = ''): Builder
    {
        if ($direction === 'from_location') {
            return $this->targets->receivers($event, search: $search);
        }

        return DB::table('people as p')->whereExists(fn ($holders) => $holders->selectRaw('1')->from('stock_holders as holder')
            ->joinSub($this->stock->balancesQuery($event->id), 'balance', 'balance.holder_id', '=', 'holder.id')
            ->where('holder.event_id', $event->id)->whereColumn('holder.person_id', 'p.id')->where('balance.balance', '>', 0))
            ->whereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", ['%'.SqlLike::escape(mb_strtolower($search)).'%'])
            ->select('p.id', 'p.name')->orderByRaw('LOWER(p.name)')->orderBy('p.id');
    }

    public function rows(Event $event, Collection $rows, bool $people = true): Collection
    {
        $balances = DB::query()->fromSub($this->stock->balancesQuery($event->id), 'balance')
            ->whereIn('holder_id', $rows->pluck('holder_id')->filter())
            ->select('holder_id', 'entitlement_item_id', 'balance')->get()->groupBy('holder_id');
        $eligible = $people ? $this->targets->receivers($event)->whereIn('p.id', $rows->pluck('id'))->pluck('p.id')->all() : [];

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id, 'name' => $row->name,
            'inactive' => $people && ! in_array($row->id, $eligible),
            'is_supervisor' => $people && (bool) $row->is_supervisor,
            'items' => ($balances[$row->holder_id] ?? collect())->map(fn ($balance) => [
                'id' => (int) $balance->entitlement_item_id, 'balance' => (int) $balance->balance,
            ])->values()->all(),
        ]);
    }
}
