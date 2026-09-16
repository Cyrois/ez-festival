<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistEngagementNote;
use App\Models\Event;
use App\Models\User;
use App\Repositories\ArtistRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArtistService
{
    public function __construct(private readonly ArtistRepository $artists) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function addToEvent(Event $event, array $data): void
    {
        DB::transaction(function () use ($event, $data) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $artist = $this->artists->findOrCreateArtist($data['name']);

            if ($artist->engagements()->where('event_id', $event->id)->exists()) {
                throw ValidationException::withMessages(['name' => __('artists.errors.already_added')]);
            }

            $engagement = $artist->engagements()->create([
                'event_id' => $event->id,
                'artist_type_id' => $data['artist_type_id'] ?? null,
                'status' => $data['status'] ?? 'idea',
            ]);

            $labelIds = $data['label_ids'] ?? [];
            foreach ($data['new_labels'] ?? [] as $label) {
                $labelIds[] = $this->artists->findOrCreateLabel(
                    $label['name'],
                    $label['color'],
                )->id;
            }

            // Labels belong to this engagement/event, not the festival-wide artist.
            $engagement->labels()->sync(array_values(array_unique($labelIds)));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEngagement(
        ArtistEngagement $engagement,
        array $data,
    ): void {
        DB::transaction(function () use ($engagement, $data) {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $artist = Artist::query()->lockForUpdate()->findOrFail($engagement->artist_id);

            $this->renameArtist($artist, $data['name']);

            $engagement->update([
                'status' => $data['status'],
                'artist_type_id' => $data['artist_type_id'] ?? null,
            ]);

            $labelIds = $data['label_ids'] ?? [];
            foreach ($data['new_labels'] ?? [] as $label) {
                $labelIds[] = $this->artists->findOrCreateLabel(
                    $label['name'],
                    $label['color'],
                )->id;
            }

            // View UI presents labels for this engagement; sync replaces this engagement's set.
            $engagement->labels()->sync(array_values(array_unique($labelIds)));
        });
    }

    public function addNote(
        ArtistEngagement $engagement,
        User $user,
        string $body,
    ): ArtistEngagementNote {
        return DB::transaction(function () use ($engagement, $user, $body) {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            return $engagement->notes()->create([
                'user_id' => $user->id,
                'body' => $body,
            ]);
        });
    }

    private function renameArtist(Artist $artist, string $name): void
    {
        $nameKey = Artist::normalizeName($name);
        $conflict = Artist::query()
            ->where('name_key', $nameKey)
            ->whereKeyNot($artist->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'name' => __('artists.errors.name_taken'),
            ]);
        }

        if ($artist->name === $name) {
            return;
        }

        try {
            $artist->update(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('artists.errors.name_taken'),
            ]);
        }
    }
}
