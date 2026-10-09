<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Support\MealClaimDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MealOverrideService
{
    public function give(Event $event, TeamEngagement $member, User $actor, int $mealId, bool $claim = true): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $mealId, $claim): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.override', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $meal = $event->meals()->lockForUpdate()->findOrFail($mealId);
            $this->ensureDay($event, $meal->date->toDateString(), $meal->starts_at, $meal->ends_at, 'meal_id');
            if (app(MealEntitlementQuery::class)->forPerson($event, $member->id)
                ->where('meal_date', $meal->date->toDateString())->where('meal_type_id', $meal->meal_type_id)->where('used', 0)->exists()) {
                return ['status' => 'unused_meal', 'message' => __('meals.override.unused', [
                    'name' => $member->person->name, 'type' => $meal->mealType->name,
                ])];
            }
            $assignment = MealAssignment::create([
                'event_id' => $event->id, 'team_engagement_id' => $member->id,
                ...app(MealAssignmentService::class)->mealAttributes($meal),
                'is_active' => true, 'is_override' => true, 'override_given_by' => $actor->id, 'override_given_at' => now(),
                'claimed_by' => $claim ? $actor->id : null, 'claimed_at' => $claim ? now() : null, 'claim_token' => $claim ? (string) Str::uuid() : null,
            ]);

            return ['status' => 'overridden', 'assignment_id' => $assignment->id, 'claim_token' => $assignment->claim_token,
                'message' => __($claim ? 'meals.override.saved' : 'meals.override.given', ['meal' => $assignment->meal_name, 'name' => $member->person->name])];
        }, 3);
    }

    public function remove(Event $event, TeamEngagement $member, User $actor, int $assignmentId): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $assignmentId): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.undo_claim', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $assignment = MealAssignment::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->where('is_override', true)->lockForUpdate()->find($assignmentId);
            if ($assignment === null) {
                return ['status' => 'already_removed', 'message' => __('meals.override.errors.removed')];
            }
            $this->ensureDay($event, $assignment->meal_date->toDateString(), $assignment->starts_at, $assignment->ends_at, 'assignment_id');
            if ($assignment->claimed_at !== null) {
                return ['status' => 'still_used', 'message' => __('meals.override.errors.used')];
            }
            $assignment->delete();

            return ['status' => 'override_removed', 'message' => __('meals.override.removed', [
                'meal' => $assignment->meal_name, 'name' => $member->person->name,
            ])];
        }, 3);
    }

    private function ensureDay(Event $event, string $date, string $start, string $end, string $field): void
    {
        if (! MealClaimDay::allows($event, $date, $start, $end)) {
            throw ValidationException::withMessages([$field => __('meals.claim.errors.day')]);
        }
    }
}
