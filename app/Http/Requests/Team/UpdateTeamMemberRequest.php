<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\TeamMemberRules;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamMemberRequest extends FormRequest
{
    use TeamMemberRules;

    protected function prepareForValidation(): void
    {
        $notes = [];
        if ($this->has('notes')) {
            $notes['notes'] = $this->trimBodies($this->input('notes'));
        }
        if ($this->has('note_edits')) {
            $notes['note_edits'] = $this->trimBodies($this->input('note_edits'));
        }

        $this->merge($notes);
    }

    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $eventId = (int) $this->route('engagement')->event_id;

        return [
            ...$this->memberRules($eventId),
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'pass_assignments' => ['sometimes', 'array'],
            'pass_assignments.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('pass_assignments', 'id'),
            ],
            'pass_assignments.*.pass_type_id' => [
                'required',
                'integer',
                Rule::exists('pass_types', 'id')->where('event_id', $eventId),
            ],
            'notes' => ['sometimes', 'array'],
            'notes.*.body' => ['required', 'string', 'max:5000'],
            'note_edits' => ['sometimes', 'array'],
            'note_edits.*.id' => ['required', 'integer', 'distinct', Rule::exists('team_engagement_notes', 'id')],
            'note_edits.*.body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $engagement = $this->route('engagement');

            if (! $validator->errors()->has('role_id') && $this->input('role_id') !== null) {
                $roleId = (int) $this->input('role_id');

                if ($roleId !== (int) $engagement->role_id
                    && ! Role::query()->whereKey($roleId)->where('active', true)->exists()) {
                    $validator->errors()->add('role_id', __('settings.team.validation.role_unavailable'));
                }
            }

            if (! $this->hasNoteChanges()) {
                return;
            }

            if (! Gate::allows('can-read-team-notes', $engagement)) {
                $validator->errors()->add('notes', __('team.member.notes.errors.forbidden'));

                return;
            }

            foreach ((array) $this->input('note_edits', []) as $index => $edit) {
                if (! is_array($edit) || ! isset($edit['id'])) {
                    continue;
                }

                $isOwnNote = $engagement->notes()
                    ->whereKey($edit['id'])
                    ->where('user_id', $this->user()->id)
                    ->exists();

                if (! $isOwnNote) {
                    $validator->errors()->add(
                        "note_edits.$index.id",
                        __('team.member.notes.errors.not_editable'),
                    );
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'pass_assignments.*.pass_type_id.exists' => __('team.member.passes.errors.foreign_pass'),
            'pass_assignments.*.pass_type_id.required' => __('team.member.passes.errors.pass_required'),
            'notes.*.body.required' => __('team.member.notes.errors.body_required'),
            'notes.*.body.max' => __('team.member.notes.errors.body_max'),
            'note_edits.*.body.required' => __('team.member.notes.errors.body_required'),
            'note_edits.*.body.max' => __('team.member.notes.errors.body_max'),
            'note_edits.*.id.exists' => __('team.member.notes.errors.not_editable'),
        ];
    }

    private function trimBodies(mixed $notes): mixed
    {
        if (! is_array($notes)) {
            return $notes;
        }

        return collect($notes)->map(function (mixed $note): mixed {
            if (is_array($note) && isset($note['body']) && is_string($note['body'])) {
                $note['body'] = trim($note['body']);
            }

            return $note;
        })->all();
    }

    private function hasNoteChanges(): bool
    {
        return $this->input('notes', []) !== [] || $this->input('note_edits', []) !== [];
    }
}
