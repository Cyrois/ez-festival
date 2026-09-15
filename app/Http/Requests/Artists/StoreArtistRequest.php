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
        return $this->user()->can('artists.manage');
    }

    public function rules(): array
    {
        $organizationId = $this->user()->primaryOrganization()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(ArtistEngagement::STATUSES)],
            'artist_type_id' => ['nullable', 'integer', Rule::exists('artist_types', 'id')->where('organization_id', $organizationId)],
            'label_ids' => ['sometimes', 'array', 'max:50'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('artist_labels', 'id')->where('organization_id', $organizationId)],
            'new_labels' => ['sometimes', 'array', 'max:20'],
            'new_labels.*' => ['array:name,color'],
            'new_labels.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'new_labels.*.color' => ['required', Rule::in(ArtistLabel::COLORS)],
        ];
    }
}
