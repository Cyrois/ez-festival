<?php

namespace App\Http\Requests\Team;

use App\Models\TeamEngagement;
use App\Services\HeldStockGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.edit');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(TeamEngagement::STATUSES)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $member = $this->route('engagement');
            if ($validator->errors()->isEmpty() && $member->status === 'hired' && $this->input('status') !== 'hired'
                && app(HeldStockGuard::class)->hasStock($member->person_id, $member->event_id)) {
                $validator->errors()->add('status', __('inventory.errors.stock_remains'));
            }
        }];
    }
}
