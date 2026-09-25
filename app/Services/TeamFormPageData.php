<?php

namespace App\Services;

use App\Models\TeamForm;
use App\Models\TeamFormField;

class TeamFormPageData
{
    /** @return array<string, mixed> */
    public function for(TeamForm $form, bool $preview = false): array
    {
        $form->loadMissing(['event', 'fields.customField']);

        return [
            'form' => [
                'name' => $form->name,
                'action' => $preview ? null : route('team.forms.public.store', $form->slug),
                'fields' => $form->fields->map(fn (TeamFormField $field): array => $this->field($field))->values(),
            ],
            'event' => ['name' => $form->event->name],
            'preview' => $preview,
        ];
    }

    /** @return array<string, mixed> */
    private function field(TeamFormField $field): array
    {
        if ($field->customField !== null) {
            return [
                'key' => "custom_fields.{$field->custom_field_id}",
                'label' => $field->customField->label,
                'type' => $field->customField->type,
                'required' => $field->required,
                'options' => $field->customField->options ?? [],
            ];
        }

        return [
            'key' => $field->key,
            'label' => __("team.forms.fields.{$field->key}"),
            'type' => $field->key === TeamFormField::KEY_EMPLOYMENT_TYPE ? 'select' : $field->key,
            'required' => $field->required,
            'options' => $field->key === TeamFormField::KEY_EMPLOYMENT_TYPE ? ['volunteer', 'paid'] : [],
        ];
    }
}
