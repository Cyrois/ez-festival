<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\VendorEngagement;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VendorRepository
{
    /**
     * @return LengthAwarePaginator<int, VendorEngagement>
     */
    public function paginateFor(?Event $event, string $search): LengthAwarePaginator
    {
        $searchPattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return VendorEngagement::query()
            ->where('event_id', $event?->id)
            ->when(
                $search !== '',
                fn (Builder $query) => $query->whereHas(
                    'vendor',
                    fn (Builder $query) => $query->whereRaw("lower(name) like ? escape '!'", [$searchPattern]),
                ),
            )
            ->with(['vendor', 'vendorType'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
    }
}
