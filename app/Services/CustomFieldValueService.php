<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomFieldValueService
{
    /**
     * @param  MorphMany<CustomFieldValue, Model>  $customFieldValues
     * @param  Collection<int, CustomField>  $customFields
     * @param  array<int|string, mixed>  $values
     */
    public function sync(MorphMany $customFieldValues, Collection $customFields, array $values): void
    {
        foreach ($customFields as $field) {
            if (! array_key_exists($field->id, $values)) {
                continue;
            }

            $value = $values[$field->id];
            $fieldValues = clone $customFieldValues;

            if ($field->type !== 'checkbox' && ($value === null || $value === '')) {
                $fieldValues->where('custom_field_id', $field->id)->delete();

                continue;
            }

            $fieldValue = $fieldValues->firstOrNew([
                'custom_field_id' => $field->id,
            ]);
            $fieldValue->setTypedValue($field, $value);
            $fieldValue->save();
        }
    }
}
