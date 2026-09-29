<?php

namespace App\Http\Requests\Settings;

use App\Repositories\RoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRoleDataTableRequest extends FormRequest
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
        return [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'between:1,100'],
            'query' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(RoleRepository::STATUSES)],
            'order' => ['sometimes', 'array', 'max:1'],
            'order.0.column' => ['sometimes', 'integer', 'in:0'],
            'order.0.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
