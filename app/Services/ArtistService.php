<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Organization;
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

            $artist->engagements()->create([
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

            // Labels belong to the reusable artist; preserve assignments from previous events.
            $artist->labels()->syncWithoutDetaching($labelIds);
        });
    }
}
