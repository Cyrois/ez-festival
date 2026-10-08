<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealClaim;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\MealEntitlementQuery;
use App\Support\MealClaimDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealClaimService
{
    public function __construct(private readonly MealEntitlementQuery $meals) {}

    public function claim(Event $event, TeamEngagement $member, User $actor, int $mealId, int $shiftId, bool $confirmed = false): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $mealId, $shiftId, $confirmed): array {
            // Scheduling, meal setup, status changes and event locking use this same first lock.
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.claim', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $meal = $event->meals()->findOrFail($mealId);
            $previous = MealClaim::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->where('source_shift_id', $shiftId)->where('meal_id', $mealId)->first();
            if ($previous !== null) {
                $this->ensureDay($event, $previous->meal_date->toDateString(), $previous->starts_at, $previous->ends_at);

                return $this->result('already_used', __('meals.claim.errors.used', [
                    'time' => $previous->claimed_at->timezone($event->timezone)->format('H:i'),
                ]));
            }
            $this->ensureDay($event, $meal->date->toDateString(), $meal->starts_at, $meal->ends_at);
            $grant = $this->meals->forPerson($event, $member->id)->where('meal_id', $mealId)
                ->where('source_shift_id', $shiftId)->where('used', 0)->first();
            if ($grant === null) {
                return $this->result('unavailable', __('meals.claim.errors.none_left', [
                    'name' => $member->person->name, 'type' => $meal->mealType->name,
                ]));
            }
            $earlier = MealClaim::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->where('meal_type_id', $grant->meal_type_id)->whereDate('meal_date', $grant->meal_date)
                ->orderBy('claimed_at')->first();
            if ($earlier !== null && ! $confirmed) {
                return $this->result('warning_required', __('meals.claim.warning', [
                    'name' => $member->person->name, 'type' => $grant->type_name,
                    'time' => $earlier->claimed_at->timezone($event->timezone)->format('H:i'),
                    'day' => $grant->meal_date === now($event->timezone)->toDateString()
                        ? __('meals.claim.today_word') : Carbon::parse($grant->meal_date)->translatedFormat('D, M j'),
                ]));
            }
            $claim = MealClaim::create([
                'event_id' => $event->id, 'team_engagement_id' => $member->id,
                'meal_id' => $mealId, 'source_shift_id' => $shiftId, 'meal_type_id' => $grant->meal_type_id,
                'shift_meal_id' => $grant->shift_meal_id, 'meal_name' => $grant->meal_name, 'meal_date' => $grant->meal_date,
                'starts_at' => $grant->starts_at, 'ends_at' => $grant->ends_at,
                'shift_location_name' => $grant->shift_location_name,
                'shift_starts_at' => $grant->shift_starts_at, 'shift_ends_at' => $grant->shift_ends_at,
                'claimed_by' => $actor->id, 'claimed_at' => now(),
                'warning_overridden_by' => $earlier ? $actor->id : null,
                'warning_overridden_at' => $earlier ? now() : null,
            ]);

            return [...$this->result('claimed', __('meals.claim.saved', [
                'meal' => $grant->meal_name, 'name' => $member->person->name,
            ])), 'claim_id' => $claim->id];
        }, 3);
    }

    public function unclaim(Event $event, TeamEngagement $member, User $actor, int $claimId): array
    {
        return DB::transaction(function () use ($event, $member, $actor, $claimId): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            abort_unless($actor->can('meals.claim', $event), 403);
            $member = $event->teamEngagements()->where('status', 'hired')->lockForUpdate()->findOrFail($member->id);
            $claim = MealClaim::query()->where('event_id', $event->id)->where('team_engagement_id', $member->id)
                ->lockForUpdate()->find($claimId);
            if ($claim === null) {
                return $this->result('already_unclaimed', __('meals.claim.errors.unclaimed'));
            }
            $this->ensureDay($event, $claim->meal_date->toDateString(), $claim->starts_at, $claim->ends_at);
            $message = __('meals.claim.unclaimed', ['meal' => $claim->meal_name, 'name' => $member->person->name]);
            // Only an explicit correction clears the durable Used state. Live grants determine availability.
            $claim->delete();

            return $this->result('unclaimed', $message);
        }, 3);
    }

    private function ensureDay(Event $event, string $day, string $start, string $end): void
    {
        if (! MealClaimDay::allows($event, $day, $start, $end)) {
            throw ValidationException::withMessages(['meal_id' => __('meals.claim.errors.day')]);
        }
    }

    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
