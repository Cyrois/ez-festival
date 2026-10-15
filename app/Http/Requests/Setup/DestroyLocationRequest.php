<?php

namespace App\Http\Requests\Setup;

use App\Models\IssuedEntitlement;
use App\Models\Location;
use App\Models\Shift;
use App\Services\StockMovementService;
use Illuminate\Foundation\Http\FormRequest;

class DestroyLocationRequest extends FormRequest
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

            if ($location !== null && (app(StockMovementService::class)->locationHasHistory($location) || IssuedEntitlement::query()->where('location_id', $location->id)->exists()
                || Shift::query()->where('location_id', $location->id)->exists())) {
                $validator->errors()->add(
                    'location',
                    __('setup.locations.errors.delete_blocked'),
                );
            }
        });
    }
}
