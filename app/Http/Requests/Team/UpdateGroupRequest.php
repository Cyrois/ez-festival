<?php

namespace App\Http\Requests\Team;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups')
                    ->where('event_id', $this->route('event')->id)
                    ->ignore($group),
            ],
        ];
    }
}
