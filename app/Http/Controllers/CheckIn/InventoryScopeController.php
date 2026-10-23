<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\InventoryScopeRequest;
use App\Http\Resources\InventoryScopeOptionResource;
use App\Services\InventoryHoldingsAccess;
use App\Support\EventContext;
use App\Support\SqlLike;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryScopeController extends Controller
{
    public function index(InventoryScopeRequest $request, EventContext $context, InventoryHoldingsAccess $access): AnonymousResourceCollection
    {
        $event = $context->requireCurrent($request->user());
        $manager = $request->user()->can('inventory.manage', $event);
        $search = '%'.SqlLike::escape(mb_strtolower(trim(($request->validated('search') ?? '')))).'%';
        if ($request->validated('type') === 'group') {
            abort_unless($manager, 403);
            $query = DB::table('groups')->where('event_id', $event->id)
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search])->select('id', 'name')->orderByRaw('LOWER(name)')->orderBy('id');
        } else {
            $now = CarbonImmutable::now($event->timezone)->format('Y-m-d H:i:s');
            $query = DB::table('shifts as s')->join('locations as l', 'l.id', '=', 's.location_id')
                ->where('s.event_id', $event->id)->whereRaw("LOWER(COALESCE(s.name, '')) LIKE ? ESCAPE '!'", [$search])
                ->when(! $manager, fn ($query) => $query->whereIn('s.id', $access->supervisedShifts($event, $request->user())->select('supervisor.shift_id')))
                ->select('s.id', 's.name', 's.starts_at', 's.ends_at', 'l.name as location')
                ->selectRaw('CASE WHEN s.starts_at <= ? AND s.ends_at > ? THEN 0 WHEN s.starts_at > ? THEN 1 ELSE 2 END as period', [$now, $now, $now])
                ->orderBy('period')->orderByRaw('CASE WHEN s.ends_at > ? THEN s.starts_at END ASC', [$now])
                ->orderByDesc('s.starts_at')->orderBy('s.id');
        }

        return InventoryScopeOptionResource::collection($query->paginate(25));
    }
}
