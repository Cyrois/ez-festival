<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassAssignment;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Models\VendorEngagementNote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorService
{
    public function __construct(private CustomFieldValueService $customFieldValueService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function addToEvent(Event $event, array $data, Collection $customFields): VendorEngagement
    {
        return DB::transaction(function () use ($event, $data, $customFields): VendorEngagement {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $name = $data['name'];
            $nameKey = Vendor::normalizeName($name);

            $vendor = Vendor::query()->where('name_key', $nameKey)->first();

            try {
                if (! $vendor) {
                    $vendor = Vendor::query()->create([
                        'name' => $name,
                        'name_key' => $nameKey,
                    ]);
                }

                if ($event->vendorEngagements()->whereBelongsTo($vendor)->exists()) {
                    throw ValidationException::withMessages([
                        'name' => __('vendors.errors.already_added'),
                    ]);
                }

                $engagement = $vendor->engagements()->create([
                    'event_id' => $event->id,
                    'vendor_type_id' => $data['vendor_type_id'] ?? null,
                    'status' => $data['status'] ?? 'idea',
                ]);

                $this->customFieldValueService->sync(
                    $vendor->customFieldValues(),
                    $customFields,
                    $data['custom_fields'] ?? [],
                    $event->id,
                );

                return $engagement;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('vendors.errors.already_added'),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>|null  $customFields
     */
    public function updateEngagement(VendorEngagement $engagement, array $data, ?Collection $customFields = null, ?User $user = null): void
    {
        DB::transaction(function () use ($engagement, $data, $customFields, $user): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($engagement->vendor_id);
            $nameKey = Vendor::normalizeName($data['name']);
            $conflict = Vendor::query()->where('name_key', $nameKey)->whereKeyNot($vendor->id)->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['name' => __('vendors.errors.name_taken')]);
            }

            try {
                $vendor->update(['name' => $data['name'], 'name_key' => $nameKey]);
                $engagement->update([
                    'status' => $data['status'],
                    'vendor_type_id' => $data['vendor_type_id'] ?? null,
                ]);

                if ($customFields !== null) {
                    $this->customFieldValueService->sync(
                        $vendor->customFieldValues(),
                        $customFields,
                        $data['custom_fields'] ?? [],
                        $event->id,
                    );
                }

                if (array_key_exists('people', $data)) {
                    $this->syncPeople($engagement, $data['people']);
                }

                if (array_key_exists('pass_assignments', $data)) {
                    $this->syncPassAssignments($engagement, $data['pass_assignments']);
                }

                if ($user !== null) {
                    foreach ($data['notes'] ?? [] as $note) {
                        $engagement->notes()->create([
                            'user_id' => $user->id,
                            'body' => trim($note['body']),
                        ]);
                    }
                }
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['name' => __('vendors.errors.name_taken')]);
            }
        });
    }

    /** @param array<int, array<string, mixed>> $people */
    private function syncPeople(VendorEngagement $engagement, array $people): void
    {
        $relation = $engagement->people();
        $existingIds = $relation->pluck('people.id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = collect($people)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach (array_diff($submittedIds, $existingIds) as $personId) {
            throw ValidationException::withMessages(['people' => __('validation.exists', ['attribute' => 'people'])]);
        }

        $relation->detach(array_diff($existingIds, $submittedIds));

        foreach ($people as $index => $data) {
            $person = isset($data['id'])
                ? Person::query()->findOrFail($data['id'])
                : Person::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'] ?: null,
                    'phone' => $data['phone'] ?: null,
                ]);

            if (isset($data['id'])) {
                $person->update([
                    'name' => $data['name'],
                    'email' => $data['email'] ?: null,
                    'phone' => $data['phone'] ?: null,
                ]);
            }

            $relation->syncWithoutDetaching([
                $person->id => ['is_primary' => ($data['is_primary'] ?? false) || ($index === 0 && ! collect($people)->contains('is_primary', true))],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $assignments */
    private function syncPassAssignments(VendorEngagement $engagement, array $assignments): void
    {
        $existing = $engagement->passAssignments()->get();
        $existingIds = $existing->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = collect($assignments)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach (array_diff($submittedIds, $existingIds) as $assignmentId) {
            throw ValidationException::withMessages(['pass_assignments' => __('validation.exists', ['attribute' => 'pass assignments'])]);
        }

        $personIds = $engagement->people()->pluck('people.id')->all();
        foreach ($assignments as $assignment) {
            abort_unless($assignment['person_id'] === null || in_array($assignment['person_id'], $personIds), 422);
        }

        $passIds = collect($assignments)->pluck('pass_id')->unique()->all();
        foreach ($passIds as $passId) {
            $pass = Pass::query()->lockForUpdate()->findOrFail($passId);
            abort_unless($pass->event_id === $engagement->event_id, 404);
            $otherAssignments = $pass->assignments()
                ->where(function ($query) use ($engagement): void {
                    $query->where('assignable_type', '!=', $engagement->getMorphClass())
                        ->orWhere('assignable_id', '!=', $engagement->id);
                })
                ->count();
            $requestedCount = collect($assignments)->where('pass_id', $passId)->count();
            if ($pass->max_assignments !== null && $otherAssignments + $requestedCount > $pass->max_assignments) {
                throw ValidationException::withMessages(['pass_assignments' => __('credentials.assignments.errors.capacity')]);
            }
        }

        $engagement->passAssignments()->whereKey(array_diff($existingIds, $submittedIds))->delete();
        foreach ($assignments as $data) {
            $assignment = isset($data['id'])
                ? PassAssignment::query()->findOrFail($data['id'])
                : $engagement->passAssignments()->make();
            $assignment->fill(['pass_id' => $data['pass_id'], 'person_id' => $data['person_id']]);
            $assignment->save();
        }
    }

    public function addNote(VendorEngagement $engagement, User $user, string $body): VendorEngagementNote
    {
        return DB::transaction(function () use ($engagement, $user, $body): VendorEngagementNote {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            return $engagement->notes()->create(['user_id' => $user->id, 'body' => $body]);
        });
    }
}
