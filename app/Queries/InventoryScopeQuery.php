<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\User;
use App\Services\InventoryHoldingsAccess;
use App\Support\SqlLike;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryScopeQuery
{
    public function __construct(private readonly InventoryHoldingsAccess $access, private readonly InventoryTargetsQuery $targets) {}

    public function options(Event $event, User $actor, array $filters): Builder
    {
        $manager = $this->targets->allows($event, $actor, 'inventory.manage', requireHired: false);
        $search = '%'.SqlLike::escape(mb_strtolower(trim($filters['search'] ?? ''))).'%';
        if ($filters['type'] === 'group') {
            abort_unless($manager, 403);

            return DB::table('groups')->where('event_id', $event->id)
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search])->select('id', 'name')->orderByRaw('LOWER(name)')->orderBy('id');
        }

        $now = CarbonImmutable::now($event->timezone)->format('Y-m-d H:i:s');

        return DB::table('shifts as s')->join('locations as l', 'l.id', '=', 's.location_id')
            ->where('s.event_id', $event->id)->whereRaw("LOWER(COALESCE(s.name, '')) LIKE ? ESCAPE '!'", [$search])
            ->when(! $manager, fn ($query) => $query->whereIn('s.id', $this->access->supervisedShifts($event, $actor)->select('supervisor.shift_id')))
            ->select('s.id', 's.name', 's.starts_at', 's.ends_at', 'l.name as location')
            ->selectRaw('CASE WHEN s.starts_at <= ? AND s.ends_at > ? THEN 0 WHEN s.starts_at > ? THEN 1 ELSE 2 END as period', [$now, $now, $now])
            ->orderBy('period')->orderByRaw('CASE WHEN s.ends_at > ? THEN s.starts_at END ASC', [$now])
            ->orderByDesc('s.starts_at')->orderBy('s.id');
    }
}
