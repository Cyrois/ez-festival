<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = $this->typesTable();

        return [
            'types' => ['required', 'array'],
            'types.*.id' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
            'types.*.position' => ['required', 'integer', 'min:0', 'distinct'],
        ];
    }

    /**
     * @param  array<string, mixed>|int|string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();
        $validated['types'] = collect($validated['types'] ?? [])
            ->sortBy('position')
            ->values()
            ->map(fn (array $type, int $index) => [
                'id' => (int) $type['id'],
                'position' => $index,
            ])
            ->all();

        if ($key === null) {
            return $validated;
        }

        return data_get($validated, $key, $default);
    }

    private function typesTable(): string
    {
        $name = (string) $this->route()?->getName();

        return str_contains($name, 'vendor-types') ? 'vendor_types' : 'artist_types';
    }
}
