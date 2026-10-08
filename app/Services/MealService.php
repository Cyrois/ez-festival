<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Meal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealService
{
    public function create(Event $event, array $data): Meal
    {
        return DB::transaction(function () use ($event, $data): Meal {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $this->ensureValid($event, $data);

            return $event->meals()->create($this->attributes($data));
        }, 3);
    }

    public function update(Event $event, Meal $meal, array $data): void
    {
        DB::transaction(function () use ($event, $meal, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $meal = $event->meals()->lockForUpdate()->findOrFail($meal->id);
            $this->ensureValid($event, $data, $meal);
            $meal->update($this->attributes($data));
        }, 3);
    }

    public function destroy(Event $event, Meal $meal): void
    {
        DB::transaction(function () use ($event, $meal): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $meal = $event->meals()->lockForUpdate()->findOrFail($meal->id);
            $errors = $this->deletionErrors($meal);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $meal->delete();
        }, 3);
    }

    /** @return array<string, string> */
    public function validationErrors(Event $event, array $data, ?Meal $ignore = null): array
    {
        $errors = $ignore !== null ? $this->editErrors($ignore) : [];
        if ($event->meals()->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->where('name_key', Meal::normalizeName($data['name']))->exists()) {
            $errors['name'] = __('meals.errors.name_taken');
        }
        if (! $event->mealTypes()->whereKey($data['meal_type_id'])->exists()) {
            $errors['meal_type_id'] = __('meals.errors.type');
        }
        if ($data['starts_at'] === $data['ends_at']) {
            $errors['ends_at'] = __('meals.errors.equal_times');
        }

        return $errors;
    }

    /** @return array<string, string> */
    public function editErrors(Meal $meal): array
    {
        return $meal->shiftMeals()->exists()
            ? ['meal' => __('meals.errors.assigned_edit', ['name' => $meal->name])]
            : [];
    }

    /** @return array<string, string> */
    public function deletionErrors(Meal $meal): array
    {
        if (DB::table('meal_claims')->where('meal_id', $meal->id)->exists()) {
            return ['meal' => __('meals.errors.used', ['name' => $meal->name])];
        }

        $shifts = $meal->shiftMeals()->with('shift.location')->get()->pluck('shift')->unique('id');
        if ($shifts->isNotEmpty()) {
            return ['meal' => __('meals.errors.assigned_delete', [
                'name' => $meal->name, 'count' => $shifts->count(),
                'shifts' => $shifts->map(fn ($shift) => $shift->location->name.', '.$shift->starts_at->format('D Y-m-d H:i').'–'.$shift->ends_at->format('Y-m-d H:i'))->implode('; '),
            ])];
        }

        return [];
    }

    private function ensureValid(Event $event, array $data, ?Meal $ignore = null): void
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
            'meal_type_id' => $data['meal_type_id'],
            'date' => $data['date'],
            'starts_at' => $data['starts_at'].':00',
            'ends_at' => $data['ends_at'].':00',
        ];
    }
}
