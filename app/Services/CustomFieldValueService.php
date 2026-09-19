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
    public function sync(
        MorphMany $customFieldValues,
        Collection $customFields,
        array $values,
        ?int $eventId = null,
    ): void {
        foreach ($customFields as $field) {
            if (! array_key_exists($field->id, $values)) {
                continue;
            }

            $value = $values[$field->id];
            $scope = [
                'custom_field_id' => $field->id,
                'event_id' => $eventId,
            ];
            $fieldValues = clone $customFieldValues;

            if ($field->type !== 'checkbox' && ($value === null || $value === '')) {
                $fieldValues->where($scope)->delete();

                continue;
            }

            $fieldValue = $fieldValues->firstOrNew($scope);
            $fieldValue->event_id = $eventId;
            $fieldValue->setTypedValue($field, $value);
            $fieldValue->save();
        }
    }
}
