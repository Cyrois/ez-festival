<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\PassAssignment;
use App\Models\PassType;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Repositories\PassTypeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamEngagementService
{
    public function __construct(
        private readonly PersonService $people,
        private readonly PassTypeRepository $passTypes,
    ) {}

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
    public function update(TeamEngagement $engagement, User $user, array $data): bool
    {
        $lateEditSkipped = DB::transaction(function () use ($engagement, $user, $data): bool {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();
            $engagement = TeamEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $hasNoteChanges = ($data['notes'] ?? []) !== [] || ($data['note_edits'] ?? []) !== [];

            if ($hasNoteChanges && Gate::forUser($user)->denies('can-read-team-notes', $engagement)) {
                throw ValidationException::withMessages([
                    'notes' => __('team.member.notes.errors.forbidden'),
                ]);
            }

            $wasHired = $engagement->status === 'hired';
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
                $engagement->passAssignments()->update(['person_id' => $target->id]);
            } else {
                if ($this->profileChanged($person, $data) && $this->isShared($person, $engagement)) {
                    throw ValidationException::withMessages([
                        'email' => __('team.advancement.errors.shared_person'),
                    ]);
                }

                $this->people->updateProfile($person, $data);
                $engagement->update($this->engagementData($data));
            }

            if (array_key_exists('pass_assignments', $data)) {
                $this->syncPassAssignments($engagement, $data['pass_assignments'], $wasHired);
            }

            foreach (array_reverse($data['notes'] ?? []) as $note) {
                $engagement->notes()->create([
                    'user_id' => $user->id,
                    'body' => $note['body'],
                ]);
            }

            $lateEditSkipped = false;
            foreach ($data['note_edits'] ?? [] as $edit) {
                $note = $engagement->notes()
                    ->whereKey($edit['id'])
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($note === null) {
                    throw ValidationException::withMessages([
                        'note_edits' => __('team.member.notes.errors.not_editable'),
                    ]);
                }

                if ($edit['body'] === $note->body) {
                    continue;
                }

                if ($note->created_at->copy()->addMinutes(5)->lessThanOrEqualTo(now())) {
                    $lateEditSkipped = true;

                    continue;
                }

                $note->update([
                    'body' => $edit['body'],
                    'edited_at' => now(),
                ]);
            }

            return $lateEditSkipped;
        });

        if (array_key_exists('pass_assignments', $data)) {
            $this->passTypes->forgetList($engagement->event_id);
        }

        return $lateEditSkipped;
    }

    public function updateStatus(TeamEngagement $engagement, string $status): void
    {
        DB::transaction(function () use ($engagement, $status): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            $engagement->update(['status' => $status]);
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
            'role_id' => $data['role_id'] ?? null,
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
            || $person->passAssignments()
                ->where(function ($query) use ($engagement): void {
                    $query->whereNull('team_engagement_id')
                        ->orWhere('team_engagement_id', '!=', $engagement->id);
                })
                ->exists()
            || $person->teamEngagements()->whereKeyNot($engagement->id)->exists();
    }

    /** @param array<int, array<string, mixed>> $assignments */
    private function syncPassAssignments(
        TeamEngagement $engagement,
        array $assignments,
        bool $wasHired,
    ): void {
        $existing = $engagement->passAssignments()
            ->with('expectedEntitlements.issuedEntitlement')
            ->lockForUpdate()
            ->get();
        $existingIds = $existing->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $submittedIds = collect($assignments)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        if (array_diff($submittedIds, $existingIds) !== []) {
            throw ValidationException::withMessages([
                'pass_assignments' => __('team.member.passes.errors.foreign_assignment'),
            ]);
        }

        $hasChanges = $this->passAssignmentsChanged($existing, $assignments);

        if ($hasChanges && ! $wasHired) {
            throw ValidationException::withMessages([
                'pass_assignments' => __('team.member.passes.errors.hired_required'),
            ]);
        }

        if (! $hasChanges) {
            return;
        }

        $savedPassTypeChanged = collect($assignments)->contains(function (array $data) use ($existing): bool {
            $assignment = isset($data['id']) ? $existing->find($data['id']) : null;

            return $assignment !== null
                && $assignment->pass_type_id !== (int) $data['pass_type_id'];
        });

        if ($savedPassTypeChanged) {
            throw ValidationException::withMessages([
                'pass_assignments' => __('team.member.passes.errors.change_saved'),
            ]);
        }

        $passTypes = [];
        foreach (collect($assignments)->pluck('pass_type_id')->unique() as $passTypeId) {
            $passType = PassType::query()
                ->with('entitlements')
                ->lockForUpdate()
                ->findOrFail($passTypeId);

            if ((int) $passType->event_id !== (int) $engagement->event_id) {
                throw ValidationException::withMessages([
                    'pass_assignments' => __('team.member.passes.errors.foreign_pass'),
                ]);
            }

            $passTypes[$passType->id] = $passType;
            $otherAssignments = $passType->assignments()
                ->where(function ($query) use ($engagement): void {
                    $query->where('team_engagement_id', '!=', $engagement->id)
                        ->orWhereNull('team_engagement_id');
                })
                ->count();
            $requestedCount = collect($assignments)->where('pass_type_id', $passTypeId)->count();

            if ($passType->max_assignments !== null
                && $otherAssignments + $requestedCount > $passType->max_assignments) {
                throw ValidationException::withMessages([
                    'pass_assignments' => __('team.member.passes.errors.capacity'),
                ]);
            }
        }

        $removed = $existing->whereIn('id', array_diff($existingIds, $submittedIds));
        if ($removed->contains(fn (PassAssignment $assignment): bool => $this->hasIssuedEntitlements($assignment))) {
            throw ValidationException::withMessages([
                'pass_assignments' => __('team.member.passes.errors.remove_issued'),
            ]);
        }

        $engagement->passAssignments()->whereKey($removed->pluck('id'))->delete();

        foreach ($assignments as $data) {
            /** @var PassAssignment|null $assignment */
            $assignment = isset($data['id']) ? $existing->find($data['id']) : null;
            $isNew = $assignment === null;

            $assignment ??= $engagement->passAssignments()->make();

            $assignment->fill([
                'pass_type_id' => $data['pass_type_id'],
                'person_id' => $engagement->person_id,
            ]);
            $assignment->save();

            if ($isNew) {
                $assignment->expectedEntitlements()->createMany(
                    $passTypes[$assignment->pass_type_id]->entitlements->map(fn ($line): array => [
                        'entitlement_item_id' => $line->entitlement_item_id,
                        'status' => ExpectedEntitlement::STATUS_EXPECTED,
                    ])->all(),
                );
            }
        }
    }

    /** @param Collection<int, PassAssignment> $existing */
    private function passAssignmentsChanged(Collection $existing, array $assignments): bool
    {
        if ($existing->count() !== count($assignments)) {
            return true;
        }

        return collect($assignments)->contains(function (array $data) use ($existing): bool {
            $assignment = isset($data['id']) ? $existing->find($data['id']) : null;

            return $assignment === null
                || $assignment->pass_type_id !== (int) $data['pass_type_id'];
        });
    }

    private function hasIssuedEntitlements(PassAssignment $assignment): bool
    {
        return $assignment->expectedEntitlements->contains(
            fn (ExpectedEntitlement $expected): bool => $expected->issuedEntitlement !== null,
        );
    }
}
