<?php

namespace App\Http\Requests\Team;

use App\Models\TeamEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexTeamAdvancementRequest extends FormRequest
{
    public const VIEW_COOKIE = 'team_advancement_view';

    private const VIEWS = ['columns', 'list'];

    protected function prepareForValidation(): void
    {
        if ($this->missing('view')) {
            $view = $this->cookie(self::VIEW_COOKIE);
            $this->merge([
                'view' => in_array($view, self::VIEWS, true) ? $view : 'columns',
            ]);
        }
    }

    public function authorize(): bool
    {
        return Gate::allows('team.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'employment_types' => ['sometimes', 'array', 'max:2'],
            'employment_types.*' => ['string', 'distinct', Rule::in(TeamEngagement::EMPLOYMENT_TYPES)],
            'view' => ['sometimes', Rule::in(self::VIEWS)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
