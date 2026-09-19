<?php

namespace App\Services;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\VendorEngagement;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class EngagementPersonService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function store(ArtistEngagement|VendorEngagement $engagement, array $data): Person
    {
        return DB::transaction(function () use ($engagement, $data): Person {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $person = Person::query()->create($data);
            $relation = $this->peopleRelation($engagement);
            $isFirst = ! $relation->exists();
            $relation->attach($person->id, ['is_primary' => $isFirst]);

            return $person;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ArtistEngagement|VendorEngagement $engagement, Person $person, array $data): void
    {
        DB::transaction(function () use ($engagement, $person, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $relation = $this->peopleRelation($engagement);
            abort_unless($relation->whereKey($person->id)->exists(), 404);
            $person->update($data);

            if (($data['is_primary'] ?? false) === true) {
                $relation->updateExistingPivot($relation->allRelatedIds(), ['is_primary' => false]);
                $relation->updateExistingPivot($person->id, ['is_primary' => true]);
            }
        });
    }

    public function destroy(ArtistEngagement|VendorEngagement $engagement, Person $person): void
    {
        DB::transaction(function () use ($engagement, $person): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $relation = $this->peopleRelation($engagement);
            $pivot = $relation->whereKey($person->id)->firstOrFail()->pivot;
            $remainingPersonId = $relation->whereKeyNot($person->id)->orderBy('people.id')->value('people.id');

            $relation->detach($person->id);

            if ($pivot->is_primary && $remainingPersonId !== null) {
                $relation->updateExistingPivot($remainingPersonId, ['is_primary' => true]);
            }
        });
    }

    /**
     * @return BelongsToMany<Person, ArtistEngagement|VendorEngagement>
     */
    private function peopleRelation(ArtistEngagement|VendorEngagement $engagement): BelongsToMany
    {
        return $engagement->people();
    }
}
