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

                return $pass;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('credentials.passes.errors.name_taken'),
                ]);
            }
        });
    }
}
