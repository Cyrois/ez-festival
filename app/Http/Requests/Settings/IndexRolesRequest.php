<?php

namespace App\Http\Requests\Settings;

use App\Repositories\RoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRolesRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(RoleRepository::STATUSES)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
