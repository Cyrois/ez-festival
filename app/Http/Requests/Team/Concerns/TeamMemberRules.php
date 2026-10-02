<?php

namespace App\Http\Requests\Team\Concerns;

use App\Models\TeamEngagement;
use Illuminate\Validation\Rule;

trait TeamMemberRules
{
    /**
     * Shared create/update rules for a member's profile and event engagement:
     * contact details, hiring status, employment type, pay and an event-scoped group.
     * Role changes, passes and notes have their own validation in the caller.
     *
     * @return array<string, mixed>
     */
    protected function memberRules(int $eventId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(TeamEngagement::STATUSES)],
            'employment_type' => ['required', Rule::in(TeamEngagement::EMPLOYMENT_TYPES)],
            'hourly_pay' => [
                'exclude_unless:employment_type,paid',
                Rule::requiredIf($this->input('employment_type') === 'paid'),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')->where('event_id', $eventId),
            ],
        ];
    }
}
