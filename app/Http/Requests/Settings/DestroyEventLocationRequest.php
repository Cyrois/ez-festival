<?php

namespace App\Http\Requests\Settings;

use App\Models\EntitlementAdjustment;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class DestroyEventLocationRequest extends FormRequest
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
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Location|null $location */
            $location = $this->route('location');

            if ($location !== null && EntitlementAdjustment::query()
                ->where('location_id', $location->id)
                ->exists()) {
                $validator->errors()->add(
                    'location',
                    __('setup.locations.errors.delete_blocked'),
                );
            }
        });
    }
}
