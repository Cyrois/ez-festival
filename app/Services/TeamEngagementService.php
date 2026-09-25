<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\TeamEngagementNote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamEngagementService
{
    public function __construct(private readonly PersonService $people) {}

    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): TeamEngagement
    {
        return DB::transaction(function () use ($event, $data): TeamEngagement {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $person = $this->people->findByEmail($data['email']);

            if ($person?->teamEngagements()->whereBelongsTo($event)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('team.advancement.errors.already_added'),
                ]);
            }

            $person ??= $this->people->findOrCreateByEmail($data);

            try {
                return TeamEngagement::query()->create([
                    'event_id' => $event->id,
                    'person_id' => $person->id,
                    ...$this->engagementData($data),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'email' => __('team.advancement.errors.already_added'),
                ]);
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TeamEngagement $engagement, array $data): void
    {
        DB::transaction(function () use ($engagement, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();
            $person = Person::query()->lockForUpdate()->findOrFail($engagement->person_id);
            $email = $this->people->normalizeEmail($data['email']);

            if ($email !== $person->email) {
                $target = $this->people->findByEmail($email);

                if ($target?->teamEngagements()->whereBelongsTo($event)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => __('team.advancement.errors.already_added'),
                    ]);
                }

                $target ??= $this->people->findOrCreateByEmail($data);
                $engagement->update([
                    'person_id' => $target->id,
                    ...$this->engagementData($data),
                ]);

                return;
            }

            if ($this->profileChanged($person, $data) && $this->isShared($person, $engagement)) {
                throw ValidationException::withMessages([
                    'email' => __('team.advancement.errors.shared_person'),
                ]);
            }

            $this->people->updateProfile($person, $data);
            $engagement->update($this->engagementData($data));
        });
    }

    public function updateStatus(TeamEngagement $engagement, string $status): void
    {
        DB::transaction(function () use ($engagement, $status): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $engagement->update(['status' => $status]);
        });
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
            'hourly_pay' => $data['hourly_pay'] ?? null,
            'group_id' => $data['group_id'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function profileChanged(Person $person, array $data): bool
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $phone = $phone === '' ? null : $phone;

        return $person->name !== $data['name'] || $person->phone !== $phone;
    }

    private function isShared(Person $person, TeamEngagement $engagement): bool
    {
        return User::query()->where('person_id', $person->id)->exists()
            || $person->artistEngagements()->exists()
            || $person->vendorEngagements()->exists()
            || $person->eventPatrons()->exists()
            || $person->passAssignments()->exists()
            || $person->teamEngagements()->whereKeyNot($engagement->id)->exists();
    }
}
