<?php

namespace App\Http\Requests\Team;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyGroupRequest extends FormRequest
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
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Group|null $group */
            $group = $this->route('group');

            if ($group !== null
                && (int) $group->event_id === (int) $this->route('event')->id
                && $group->teamEngagements()->exists()) {
                $validator->errors()->add(
                    'group',
                    __('team.configure.groups.errors.delete_blocked'),
                );
            }
        });
    }
}
