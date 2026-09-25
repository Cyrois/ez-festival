<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\TeamEngagement;
use App\Models\TeamForm;
use App\Models\TeamFormField;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamFormService
{
    public function __construct(
        private readonly PersonService $people,
        private readonly CustomFieldValueService $customFieldValues,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): TeamForm
    {
        return DB::transaction(function () use ($event, $data): TeamForm {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $form = TeamForm::query()->create([
                'event_id' => $event->id,
                'name' => $data['name'],
                'status' => $data['status'],
                'public_token' => Str::random(48),
            ]);

            $this->syncFields($form, $data['fields']);

            return $form;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(TeamForm $form, array $data): void
    {
        DB::transaction(function () use ($form, $data): void {
            $form = TeamForm::query()->lockForUpdate()->with('event')->findOrFail($form->id);
            $form->event->ensureWritable();
            $form->update(['name' => $data['name'], 'status' => $data['status']]);
            $this->syncFields($form, $data['fields']);
        });
    }

    /** @param array<string, mixed> $data */
    public function submit(TeamForm $form, array $data): TeamEngagement
    {
        return DB::transaction(function () use ($form, $data): TeamEngagement {
            $form = TeamForm::query()->lockForUpdate()->with('event')->findOrFail($form->id);
            abort_unless($form->status === 'live', 404);
            $form->event->ensureWritable();

            $person = $this->people->findByEmail($data['email'] ?? null);

            if ($person?->teamEngagements()->whereBelongsTo($form->event)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('team.forms.public.errors.already_member'),
                ]);
            }

            $person ??= $this->people->findOrCreateByEmail($data);

            try {
                $engagement = TeamEngagement::query()->create([
                    'event_id' => $form->event_id,
                    'person_id' => $person->id,
                    'team_form_id' => $form->id,
                    'status' => TeamEngagement::startingStatus(),
                    'employment_type' => $data['employment_type'] ?? TeamEngagement::EMPLOYMENT_TYPES[0],
                    'submitted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'email' => __('team.forms.public.errors.already_member'),
                ]);
            }

            $customFields = CustomField::query()
                ->whereKey(array_keys($data['custom_fields'] ?? []))
                ->where('target', CustomField::TARGET_TEAM_MEMBER)
                ->get();
            $this->customFieldValues->sync(
                $engagement->customFieldValues(),
                $customFields,
                $data['custom_fields'] ?? [],
                $form->event_id,
            );

            return $engagement;
        });
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function syncFields(TeamForm $form, array $fields): void
    {
        $retainedIds = [];

        foreach ($fields as $sortOrder => $fieldData) {
            if (isset($fieldData['id'])) {
                $field = $form->fields()->whereKey($fieldData['id'])->firstOrFail();
            } elseif (in_array($fieldData['key'], TeamFormField::BUILTIN_KEYS, true)) {
                $field = $form->fields()->firstOrNew(['key' => $fieldData['key']]);
            } else {
                $field = new TeamFormField;
            }

            $field->team_form_id = $form->id;

            if (in_array($fieldData['key'], TeamFormField::BUILTIN_KEYS, true)) {
                $field->custom_field_id = null;
                $field->key = $fieldData['key'];
            } else {
                $customField = $field->customField ?? CustomField::query()->create([
                    'target' => CustomField::TARGET_TEAM_MEMBER,
                    'label' => $fieldData['label'],
                    'key' => 'team_form_'.$form->id.'_'.Str::lower(Str::random(12)),
                    'type' => $fieldData['type'],
                    'required' => (bool) $fieldData['required'],
                    'options' => $fieldData['options'] ?? [],
                    'sort_order' => CustomField::query()->where('target', CustomField::TARGET_TEAM_MEMBER)->max('sort_order') + 1,
                    'active' => true,
                ]);

                $customField->update([
                    'label' => $fieldData['label'],
                    'type' => $fieldData['type'],
                    'required' => (bool) $fieldData['required'],
                    'options' => $fieldData['options'] ?? [],
                ]);
                $field->custom_field_id = $customField->id;
                $field->key = $field->exists ? $field->key : 'custom_'.$customField->id;
            }

            $field->required = $fieldData['key'] === TeamFormField::KEY_NAME || (bool) $fieldData['required'];
            $field->sort_order = $sortOrder;
            $field->save();
            $retainedIds[] = $field->id;
        }

        $form->fields()->whereNotIn('id', $retainedIds)->delete();
    }
}
