<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AccountService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function update(User $user, array $data, Collection $customFields): void
    {
        DB::transaction(function () use ($customFields, $data, $user): void {
            $user->update(Arr::except($data, 'custom_fields'));

            $this->syncCustomFieldValues(
                $user,
                $customFields,
                $data['custom_fields'] ?? [],
            );
        });
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    /**
     * @param  Collection<int, CustomField>  $customFields
     * @param  array<int|string, mixed>  $values
     */
    private function syncCustomFieldValues(User $user, Collection $customFields, array $values): void
    {
        foreach ($customFields as $field) {
            if (! array_key_exists($field->id, $values)) {
                continue;
            }

            $value = $values[$field->id];

            if ($field->type !== 'checkbox' && ($value === null || $value === '')) {
                $user->customFieldValues()
                    ->where('custom_field_id', $field->id)
                    ->delete();

                continue;
            }

            $fieldValue = $user->customFieldValues()->firstOrNew([
                'custom_field_id' => $field->id,
            ]);
            $fieldValue->setTypedValue($field, $value);
            $fieldValue->save();
        }
    }
}
