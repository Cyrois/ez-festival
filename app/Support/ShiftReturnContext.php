<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ShiftReturnContext
{
    public static function rules(): array
    {
        return [
            'return_to_schedule' => ['sometimes', 'boolean'],
            'return_tab' => ['sometimes', Rule::in(['schedule', 'list'])],
            'schedule_date' => ['sometimes', 'date_format:Y-m-d'],
            'schedule_location_id' => ['sometimes', 'integer', 'min:1'],
            'schedule_view' => ['sometimes', Rule::in(['all_locations', 'location_shifts'])],
        ];
    }

    public static function from(array $data): array
    {
        return array_intersect_key($data, self::rules());
    }

    public static function without(array $data): array
    {
        return array_diff_key($data, self::rules());
    }

    public static function schedulingParameters(array $data, ?string $defaultDate = null): array
    {
        $context = self::from($data);
        $date = $context['schedule_date'] ?? $defaultDate;

        return ['tab' => $context['return_tab'] ?? 'list', ...($date !== null ? ['date' => $date] : []), ...(isset($context['schedule_view']) ? ['view' => $context['schedule_view']] : []), ...(isset($context['schedule_location_id']) ? ['location_id' => $context['schedule_location_id']] : [])];
    }
}
