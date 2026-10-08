<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Meal;
use App\Models\MealAssignment;
use App\Models\ShiftMeal;
use App\Models\TeamEngagement;
use Illuminate\Support\Facades\DB;

class MealAssignmentService
{
    /** Assign a meal directly to a person, without a Scheduling source. */
    public function assign(Event $event, TeamEngagement $member, Meal $meal): MealAssignment
    {
        return DB::transaction(function () use ($event, $member, $meal): MealAssignment {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $meal = $event->meals()->findOrFail($meal->id);

            return MealAssignment::create([
                'event_id' => $event->id, 'team_engagement_id' => $member->id,
                ...$this->mealAttributes($meal), 'is_active' => true,
            ]);
        }, 3);
    }

    /** Reconcile Scheduling's selected recipients with person-owned assignments. */
    public function syncShiftMeal(ShiftMeal $row, array $assignmentIds): void
    {
        DB::transaction(function () use ($row, $assignmentIds): void {
            $event = Event::query()->lockForUpdate()->findOrFail($row->shift->event_id);
            $event->ensureWritable();
            $row = ShiftMeal::query()->with('meal', 'shift.location')->lockForUpdate()->findOrFail($row->id);
            abort_unless((int) $row->meal->event_id === (int) $event->id, 404);
            $recipients = $row->shift->assignments()->with('teamEngagement')->whereIn('id', $assignmentIds)->get();
            abort_unless($recipients->count() === count(array_unique($assignmentIds)), 404);
            MealAssignment::query()->where('shift_meal_id', $row->id)->whereNotIn('shift_assignment_id', $assignmentIds)
                ->update(['is_active' => false]);
            foreach ($recipients as $recipient) {
                abort_unless((int) $recipient->teamEngagement->event_id === (int) $event->id, 404);
                $assignment = MealAssignment::query()->firstOrNew([
                    'team_engagement_id' => $recipient->team_engagement_id,
                    'source_shift_id' => $row->shift_id, 'meal_id' => $row->meal_id,
                ]);
                $attributes = ['event_id' => $event->id, 'shift_meal_id' => $row->id,
                    'shift_assignment_id' => $recipient->id, 'is_active' => true];
                if ($assignment->claimed_at === null) {
                    $attributes = [...$attributes, ...$this->mealAttributes($row->meal),
                        'shift_location_name' => $row->shift->location->name,
                        'shift_starts_at' => $row->shift->starts_at, 'shift_ends_at' => $row->shift->ends_at];
                }
                $assignment->fill($attributes)->save();
            }
        }, 3);
    }

    public function mealAttributes(Meal $meal): array
    {
        return ['meal_id' => $meal->id, 'meal_type_id' => $meal->meal_type_id, 'meal_name' => $meal->name,
            'meal_date' => $meal->date, 'starts_at' => $meal->starts_at, 'ends_at' => $meal->ends_at];
    }
}
