<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\TeamEngagementNote;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamEngagementService
{
    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): TeamEngagement
    {
        return DB::transaction(function () use ($event, $data): TeamEngagement {
            $email = $this->normalizeEmail($data['email'] ?? null);
            $person = $email === null
                ? new Person
                : (Person::query()->whereRaw('lower(email) = ?', [$email])->first() ?? new Person);

            if ($person->exists && $person->teamEngagements()->whereBelongsTo($event)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('team.advancement.errors.already_added'),
                ]);
            }

            $person->fill(Arr::only($data, ['name', 'phone']));
            $person->email = $email;
            $person->save();

            return TeamEngagement::query()->create([
                'event_id' => $event->id,
                'person_id' => $person->id,
                ...$this->engagementData($data),
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TeamEngagement $engagement, array $data): void
    {
        DB::transaction(function () use ($engagement, $data): void {
            $email = $this->normalizeEmail($data['email'] ?? null);

            if ($email !== null) {
                $duplicate = TeamEngagement::query()
                    ->where('event_id', $engagement->event_id)
                    ->whereKeyNot($engagement->id)
                    ->whereHas('person', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'email' => __('team.advancement.errors.already_added'),
                    ]);
                }
            }

            $engagement->person->fill(Arr::only($data, ['name', 'phone']));
            $engagement->person->email = $email;
            $engagement->person->save();
            $engagement->update($this->engagementData($data));
        });
    }

    public function updateStatus(TeamEngagement $engagement, string $status): void
    {
        $engagement->update(['status' => $status]);
    }

    public function addNote(
        TeamEngagement $engagement,
        User $user,
        string $body,
    ): TeamEngagementNote {
        return DB::transaction(function () use ($engagement, $user, $body): TeamEngagementNote {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            return $engagement->notes()->create([
                'user_id' => $user->id,
                'body' => $body,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function engagementData(array $data): array
    {
        return [
            'status' => $data['status'],
            'employment_type' => $data['employment_type'],
            'role_title' => $data['role_title'] ?? null,
            'hourly_pay' => $data['employment_type'] === 'paid' ? $data['hourly_pay'] : null,
            'group_id' => $data['group_id'] ?? null,
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }
}
