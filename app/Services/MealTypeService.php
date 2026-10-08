<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealType;
use App\Support\MealWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealTypeService
{
    public function create(Event $event, array $data): MealType
    {
        return DB::transaction(function () use ($event, $data): MealType {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $this->ensureValid($event, $data);

            return $event->mealTypes()->create([
                ...$this->attributes($data),
                'sort_order' => (int) $event->mealTypes()->max('sort_order') + 1,
            ]);
        }, 3);
    }

    public function update(Event $event, MealType $type, array $data): void
    {
        DB::transaction(function () use ($event, $type, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $type = $event->mealTypes()->lockForUpdate()->findOrFail($type->id);
            $this->ensureValid($event, $data, $type);
            $type->update($this->attributes($data));
        }, 3);
    }

    /** @return array<string, string> */
    public function validationErrors(Event $event, array $data, ?MealType $ignore = null): array
    {
        $types = $event->mealTypes()->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'name_key', 'starts_at', 'ends_at']);
        $errors = [];

        if ($types->contains('name_key', MealType::normalizeName($data['name']))) {
            $errors['name'] = __('meals.types.errors.name_taken');
        }

        if ($data['starts_at'] === $data['ends_at']) {
            $errors['ends_at'] = __('meals.types.errors.equal_times');
        } else {
            $clash = $types->first(fn (MealType $type): bool => MealWindow::overlaps(
                $data['starts_at'], $data['ends_at'], $type->starts_at, $type->ends_at,
            ));
            if ($clash !== null) {
                $errors['ends_at'] = __('meals.types.errors.overlap', [
                    'name' => $clash->name,
                    'start' => substr($clash->starts_at, 0, 5),
                    'end' => substr($clash->ends_at, 0, 5),
                ]);
            }
        }

        return $errors;
    }

    private function ensureValid(Event $event, array $data, ?MealType $ignore = null): void
    {
        $errors = $this->validationErrors($event, $data, $ignore);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'starts_at' => $data['starts_at'].':00',
            'ends_at' => $data['ends_at'].':00',
        ];
    }
}
