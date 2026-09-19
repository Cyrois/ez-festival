<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassService
{
    public function __construct(private readonly CustomFieldValueService $customFieldValueService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function create(Event $event, array $data, Collection $customFields): Pass
    {
        return DB::transaction(function () use ($event, $data, $customFields): Pass {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            try {
                $pass = $event->passes()->create([
                    'name' => $data['name'],
                    'max_assignments' => $data['max_assignments'] ?? null,
                ]);

                $this->syncDetails($pass, $data, $customFields, $event);

                return $pass;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('credentials.passes.errors.name_taken'),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function update(Pass $pass, array $data, Collection $customFields): void
    {
        DB::transaction(function () use ($pass, $data, $customFields): void {
            $pass = Pass::query()->lockForUpdate()->findOrFail($pass->id);
            $event = Event::query()->lockForUpdate()->findOrFail($pass->event_id);
            $event->ensureWritable();

            $maxAssignments = $data['max_assignments'] ?? null;

            if ($maxAssignments !== null && $pass->assignments()->count() > $maxAssignments) {
                throw ValidationException::withMessages([
                    'max_assignments' => __('credentials.passes.errors.capacity_below_assigned'),
                ]);
            }

            try {
                $pass->update([
                    'name' => $data['name'],
                    'max_assignments' => $maxAssignments,
                ]);
                $this->syncDetails($pass, $data, $customFields, $event);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('credentials.passes.errors.name_taken'),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    private function syncDetails(Pass $pass, array $data, Collection $customFields, Event $event): void
    {
        $labelIds = $data['label_ids'] ?? [];

        foreach ($data['new_labels'] ?? [] as $label) {
            $nameKey = PassLabel::normalizeName($label['name']);
            $savedLabel = PassLabel::query()->firstOrCreate(
                ['name_key' => $nameKey],
                ['name' => $label['name'], 'color' => $label['color']],
            );
            $labelIds[] = $savedLabel->id;
        }

        $pass->labels()->sync(array_values(array_unique($labelIds)));

        $this->customFieldValueService->sync(
            $pass->customFieldValues(),
            $customFields,
            $data['custom_fields'] ?? [],
            $event->id,
        );
    }
}
