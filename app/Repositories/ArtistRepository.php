<?php

namespace App\Repositories;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use App\Models\Event;
use App\Models\Organization;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

class ArtistRepository
{
    /**
     * @param  list<int>  $labelIds
     * @return LengthAwarePaginator<int, ArtistEngagement>
     */
    public function paginateEngagements(
        Organization $organization,
        ?Event $event,
        string $search,
        array $labelIds,
    ): LengthAwarePaginator {
        $searchPattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return ArtistEngagement::query()
            ->where('event_id', $event?->id)
            ->whereHas('artist', function (Builder $query) use ($organization, $search, $searchPattern, $labelIds) {
                $query->where('organization_id', $organization->id)
                    ->when(
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
    public function labelsFor(Organization $organization): Collection
    {
        return $organization->artistLabels()->orderBy('name')->get(['id', 'name', 'color']);
    }

    public function findOrCreateArtist(Organization $organization, string $name): Artist
    {
        /** @var Artist */
        return $this->findOrCreateByName($organization->artists(), $name);
    }

    public function findOrCreateLabel(Organization $organization, string $name, string $color): ArtistLabel
    {
        /** @var ArtistLabel */
        return $this->findOrCreateByName($organization->artistLabels(), $name, ['color' => $color]);
    }

    /**
     * @param  HasMany<Model, Organization>  $relation
     * @param  array<string, mixed>  $attributes
     */
    private function findOrCreateByName(HasMany $relation, string $name, array $attributes = []): Model
    {
        $existing = (clone $relation->getQuery())
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return $relation->create(['name' => $name, ...$attributes]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = (clone $relation->getQuery())
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }
}
