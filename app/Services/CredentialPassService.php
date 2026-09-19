<?php

namespace App\Services;

use App\Models\CredentialPass;
use App\Models\CredentialPassLabel;
use App\Models\CustomField;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CredentialPassService
{
    public function __construct(private readonly CustomFieldValueService $customFieldValueService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function create(Event $event, array $data, Collection $customFields): CredentialPass
    {
        return DB::transaction(function () use ($event, $data, $customFields): CredentialPass {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            try {
                $pass = $event->credentialPasses()->create([
                    'name' => $data['name'],
                    'max_assignments' => $data['max_assignments'] ?? null,
                ]);

                $labelIds = $data['label_ids'] ?? [];

                foreach ($data['new_labels'] ?? [] as $label) {
                    $nameKey = CredentialPassLabel::normalizeName($label['name']);
                    $savedLabel = CredentialPassLabel::query()->firstOrCreate(
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
