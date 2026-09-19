<?php

namespace App\Services;

use App\Models\CustomField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CustomFieldService
{
    /**
     * @return Builder<CustomField>
     */
    public function fields(?string $target = null): Builder
    {
        $fields = CustomField::query();

        if ($target !== null) {
            $fields->forTarget($target);
        }

        return $fields
            ->orderBy('target')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomField
    {
        $target = $data['target'];

        return CustomField::query()->create([
            'target' => $target,
            'label' => $data['label'],
            'key' => $this->nextKey($data['label'], $target),
            'type' => $data['type'],
            'required' => $data['required'] ?? false,
            'options' => $this->optionsFor($data),
            'sort_order' => ((int) $this->fields($target)->max('sort_order')) + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomField $customField, array $data): void
    {
        $customField->update([
            'label' => $data['label'],
            'type' => $data['type'],
            'required' => $data['required'] ?? $customField->required,
            'active' => $data['active'] ?? true,
            'options' => $this->optionsFor($data),
        ]);
    }

    public function delete(CustomField $customField): void
    {
        $customField->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function optionsFor(array $data): ?array
    {
        if ($data['type'] !== 'select') {
            return null;
        }

        return array_values($data['options'] ?? []);
    }

    private function nextKey(string $label, string $target): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $suffix = 2;

        while ($this->fields($target)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
