<?php

namespace App\Http\Requests\Artists;

use App\Models\ArtistEngagement;
use App\Models\ArtistLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        if ($this->has('new_labels') && is_array($this->input('new_labels'))) {
            $this->merge([
                'new_labels' => array_map(function ($label) {
                    if (! is_array($label) || ! array_key_exists('name', $label)) {
                        return $label;
                    }

                    return [...$label, 'name' => trim((string) $label['name'])];
                }, $this->input('new_labels')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(ArtistEngagement::STATUSES)],
            'artist_type_id' => ['nullable', 'integer', Rule::exists('artist_types', 'id')],
            'label_ids' => ['sometimes', 'array', 'max:50'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('artist_labels', 'id')],
            'new_labels' => ['sometimes', 'array', 'max:20'],
            'new_labels.*' => ['array:name,color'],
            'new_labels.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'new_labels.*.color' => ['required', Rule::in(ArtistLabel::COLORS)],
        ];
    }
}
