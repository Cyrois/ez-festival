<?php

namespace App\Http\Requests\Kitchen\Concerns;

use App\Services\MealTypeService;
use App\Support\EventContext;
use Illuminate\Validation\Validator;

trait MealTypeRules
{
    public function authorize(): bool
    {
        $event = app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        $type = $this->route('mealType');
        abort_if($type !== null && (int) $type->event_id !== (int) $event->id, 404);

        return $this->user()->can('meals.edit', $event);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:50'],
            'starts_at' => ['bail', 'required', 'date_format:H:i'],
            'ends_at' => ['bail', 'required', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('meals.types.name'),
            'starts_at' => __('meals.types.start'),
            'ends_at' => __('meals.types.end'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $errors = app(MealTypeService::class)->validationErrors(
                $this->route('event'), $validator->validated(), $this->route('mealType'),
            );
            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
