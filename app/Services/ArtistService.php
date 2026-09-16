<?php

namespace App\Services;

use App\Models\Event;
use App\Repositories\ArtistRepository;
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

            $artist->engagements()->create([
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

            // Labels belong to the reusable artist; preserve assignments from previous events.
            $artist->labels()->syncWithoutDetaching($labelIds);
        });
    }
}
