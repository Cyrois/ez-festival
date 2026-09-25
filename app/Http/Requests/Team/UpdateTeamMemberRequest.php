<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\TeamMemberRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTeamMemberRequest extends FormRequest
{
    use TeamMemberRules;

    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->memberRules((int) $this->route('engagement')->event_id);
    }
}
