<?php

namespace App\Repositories;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ArtistRepository
{
    /**
     * @param  list<int>  $labelIds
     * @return LengthAwarePaginator<int, ArtistEngagement>
     */
    public function paginateEngagements(
        ?Event $event,
        string $search,
        array $labelIds,
    ): LengthAwarePaginator {
        $searchPattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return ArtistEngagement::query()
            ->where('event_id', $event?->id)
            ->whereHas('artist', function (Builder $query) use ($search, $searchPattern, $labelIds) {
                $query->when(
                    $search !== '',
                    fn (Builder $query) => $query->whereRaw("lower(name) like ? escape '!'", [$searchPattern]),
                );

                foreach ($labelIds as $labelId) {
                    $query->whereHas('labels', fn (Builder $query) => $query->whereKey($labelId));
                }
            })
            ->with(['artist.labels' => fn ($query) => $query->orderBy('name'), 'artistType'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @return Collection<int, ArtistLabel>
     */
    public function labelsFor(): Collection
    {
        return ArtistLabel::query()->orderBy('name')->get(['id', 'name', 'color']);
    }

    public function findOrCreateArtist(string $name): Artist
    {
        $nameKey = Artist::normalizeName($name);
        $now = now();

        Artist::query()->insertOrIgnore([
            'name' => $name,
            'name_key' => $nameKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Artist::query()->where('name_key', $nameKey)->firstOrFail();
    }

    public function findOrCreateLabel(string $name, string $color): ArtistLabel
    {
        $nameKey = ArtistLabel::normalizeName($name);
        $now = now();

        ArtistLabel::query()->insertOrIgnore([
            'name' => $name,
            'name_key' => $nameKey,
            'color' => $color,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ArtistLabel::query()->where('name_key', $nameKey)->firstOrFail();
    }
}
