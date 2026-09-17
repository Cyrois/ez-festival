<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Vendor;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VendorRepository
{
    /**
     * @return LengthAwarePaginator<int, Vendor>
     */
    public function paginateFor(?Event $event, string $search): LengthAwarePaginator
    {
        $searchPattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return Vendor::query()
            ->where('event_id', $event?->id)
            ->when(
                $search !== '',
                fn (Builder $query) => $query->whereRaw("lower(name) like ? escape '!'", [$searchPattern]),
            )
            ->with('vendorType')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
    }
}
