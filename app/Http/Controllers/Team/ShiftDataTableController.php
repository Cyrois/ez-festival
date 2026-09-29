<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexShiftDataTableRequest;
use App\Http\Responses\Team\ShiftDataTableResponse;
use App\Models\Location;
use App\Models\Shift;
use App\Support\EventContext;
use Illuminate\Database\Eloquent\Builder;

class ShiftDataTableController extends Controller
{
    public function index(
        IndexShiftDataTableRequest $request,
        EventContext $eventContext,
    ): ShiftDataTableResponse {
        $event = $eventContext->requireCurrent($request->user());
        $validated = $request->validated();
        $start = (int) ($validated['start'] ?? 0);
        $length = (int) ($validated['length'] ?? 25);
        $search = trim((string) data_get($validated, 'search.value', ''));

        $query = Shift::query()
            ->whereBelongsTo($event)
            ->with('location:id,name');
        $recordsTotal = (clone $query)->count();

        if ($search !== '') {
            $normalizedSearch = '%'.mb_strtolower($search).'%';

            $query->where(function (Builder $query) use ($normalizedSearch): void {
                $query
                    ->whereRaw('LOWER(shifts.name) LIKE ?', [$normalizedSearch])
                    ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery
                        ->whereRaw('LOWER(locations.name) LIKE ?', [$normalizedSearch]));
            });
        }

        $recordsFiltered = (clone $query)->count();
        $this->applyOrder(
            $query,
            (int) data_get($validated, 'order.0.column', 2),
            (string) data_get($validated, 'order.0.dir', 'asc'),
        );

        $shifts = $query
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'location' => $shift->location->name,
                'starts_at' => $shift->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $shift->ends_at->format('Y-m-d\TH:i'),
            ])
            ->all();

        return new ShiftDataTableResponse(
            draw: (int) ($validated['draw'] ?? 0),
            recordsTotal: $recordsTotal,
            recordsFiltered: $recordsFiltered,
            data: $shifts,
        );
    }

    private function applyOrder(Builder $query, int $column, string $direction): void
    {
        match ($column) {
            0 => $query->orderByRaw("LOWER(COALESCE(shifts.name, '')) {$direction}"),
            1 => $query->orderBy(
                Location::query()
                    ->select('name')
                    ->whereColumn('locations.id', 'shifts.location_id'),
                $direction,
            ),
            2 => $query->orderBy('starts_at', $direction),
            default => $query->orderBy('ends_at', $direction),
        };

        $query->orderBy('shifts.id');
    }
}
