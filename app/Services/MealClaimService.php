<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealAssignment;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\MealClaimDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MealClaimService
{
    public function claim(Event $event, TeamEngagement $member, User $actor, int $assignmentId, bool $confirmed = false): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $assignmentId, $confirmed): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.claim', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $assignment = MealAssignment::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->lockForUpdate()->findOrFail($assignmentId);
            $this->ensureDay($event, $assignment->meal_date->toDateString(), $assignment->starts_at, $assignment->ends_at);
            if ($assignment->claimed_at !== null) {
                return $this->result('already_used', __('meals.claim.errors.used', [
                    'time' => $assignment->claimed_at->timezone($event->timezone)->format('H:i'),
                ]));
            }
            if (! $assignment->is_active || ($assignment->source_shift_id !== null
                && ($assignment->shift_meal_id === null || $assignment->shift_assignment_id === null))) {
                return $this->result('unavailable', __('meals.claim.errors.none_left', [
                    'name' => $member->person->name, 'type' => $assignment->mealType->name,
                ]));
            }
            $earlier = MealAssignment::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->whereNotNull('claimed_at')->where('meal_type_id', $assignment->meal_type_id)->whereDate('meal_date', $assignment->meal_date)
                ->orderBy('claimed_at')->first();
            if ($earlier !== null && ! $confirmed) {
                return $this->result('warning_required', __('meals.claim.warning', [
                    'name' => $member->person->name, 'type' => $assignment->mealType->name,
                    'time' => $earlier->claimed_at->timezone($event->timezone)->format('H:i'),
                    'day' => $assignment->meal_date->toDateString() === now($event->timezone)->toDateString()
                        ? __('meals.claim.today_word') : $assignment->meal_date->translatedFormat('D, M j'),
                ]));
            }
            $assignment->update(['claimed_by' => $actor->id, 'claimed_at' => now(), 'claim_token' => (string) Str::uuid(),
                'warning_overridden_by' => $earlier ? $actor->id : null,
                'warning_overridden_at' => $earlier ? now() : null]);

            return [...$this->result('claimed', __('meals.claim.saved', [
                'meal' => $assignment->meal_name, 'name' => $member->person->name,
            ])), 'assignment_id' => $assignment->id, 'claim_token' => $assignment->claim_token];
        }, 3);
    }

    public function unclaim(Event $event, TeamEngagement $member, User $actor, int $assignmentId, string $claimToken): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $assignmentId, $claimToken): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.claim', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $assignment = MealAssignment::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->lockForUpdate()->findOrFail($assignmentId);
            if ($assignment->claimed_at === null || $assignment->claim_token !== $claimToken) {
                return $this->result('already_unclaimed', __('meals.claim.errors.unclaimed'));
            }
            $this->ensureDay($event, $assignment->meal_date->toDateString(), $assignment->starts_at, $assignment->ends_at);
            $assignment->update(['claimed_at' => null, 'claimed_by' => null, 'claim_token' => null,
                'warning_overridden_at' => null, 'warning_overridden_by' => null]);

            return $this->result('unclaimed', __('meals.claim.unclaimed', [
                'meal' => $assignment->meal_name, 'name' => $member->person->name,
            ]));
        }, 3);
    }

    private function ensureDay(Event $event, string $day, string $start, string $end): void
    {
        if (! MealClaimDay::allows($event, $day, $start, $end)) {
            throw ValidationException::withMessages(['assignment_id' => __('meals.claim.errors.day')]);
        }
    }

    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
