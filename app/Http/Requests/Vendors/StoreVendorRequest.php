<?php

namespace App\Http\Requests\Vendors;

use App\Models\VendorEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(VendorEngagement::STATUSES)],
            'vendor_type_id' => ['nullable', 'integer', Rule::exists('vendor_types', 'id')],
        ];
    }
}
