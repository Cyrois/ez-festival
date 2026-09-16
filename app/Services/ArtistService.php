<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\ArtistEngagementNote;
use App\Models\Event;
use App\Models\Organization;
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
    public function addToEvent(Organization $organization, Event $event, array $data): void
    {
        DB::transaction(function () use ($organization, $event, $data) {
            // The organization lock serializes case-insensitive artist and label creation.
            Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable($organization);

            try {
                $artist = $this->artists->findOrCreateArtist($organization, $data['name']);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('artists.errors.name_taken'),
                ]);
            }

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
                try {
                    $labelIds[] = $this->artists->findOrCreateLabel(
                        $organization,
                        $label['name'],
                        $label['color'],
                    )->id;
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'new_labels' => __('artists.errors.label_taken'),
                    ]);
                }
            }

            // Labels belong to this engagement/event, not the org-wide artist.
            $engagement->labels()->sync(array_values(array_unique($labelIds)));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEngagement(
        Organization $organization,
        ArtistEngagement $engagement,
        array $data,
    ): void {
        DB::transaction(function () use ($organization, $engagement, $data) {
            Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable($organization);

            $artist = Artist::query()->lockForUpdate()->findOrFail($engagement->artist_id);

            $this->renameArtist($organization, $artist, $data['name']);

            $engagement->update([
                'status' => $data['status'],
                'artist_type_id' => $data['artist_type_id'] ?? null,
            ]);

            $labelIds = $data['label_ids'] ?? [];
            foreach ($data['new_labels'] ?? [] as $label) {
                try {
                    $labelIds[] = $this->artists->findOrCreateLabel(
                        $organization,
                        $label['name'],
                        $label['color'],
                    )->id;
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'new_labels' => __('artists.errors.label_taken'),
                    ]);
                }
            }

            // View UI presents labels for this engagement; sync replaces this engagement's set.
            $engagement->labels()->sync(array_values(array_unique($labelIds)));
        });
    }

    public function addNote(
        Organization $organization,
        ArtistEngagement $engagement,
        User $user,
        string $body,
    ): ArtistEngagementNote {
        return DB::transaction(function () use ($organization, $engagement, $user, $body) {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable($organization);

            return $engagement->notes()->create([
                'user_id' => $user->id,
                'body' => $body,
            ]);
        });
    }

    private function renameArtist(Organization $organization, Artist $artist, string $name): void
    {
        $conflict = Artist::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
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
