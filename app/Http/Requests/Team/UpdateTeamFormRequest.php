<?php

namespace App\Http\Requests\Team;

use App\Models\TeamForm;
use Illuminate\Validation\Rule;

class UpdateTeamFormRequest extends StoreTeamFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $form = $this->route('teamForm');

        return [
            ...$this->formRules(),
            'fields.*.id' => [
                'nullable',
                'integer',
                Rule::exists('team_form_fields', 'id')->where('team_form_id', $form instanceof TeamForm ? $form->id : 0),
            ],
        ];
    }
}
