<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Database\Query\Builder;

final class MealClaimDay
{
    public static function scope(Builder $query, Event $event): Builder
    {
        $now = now($event->timezone);

        return $query->where(fn (Builder $days) => $days
            ->where('meal_date', $now->toDateString())
            ->orWhere(fn (Builder $overnight) => $overnight
                ->where('meal_date', $now->copy()->subDay()->toDateString())
                ->whereColumn('ends_at', '<', 'starts_at')
                ->where('ends_at', '>=', $now->format('H:i:s'))));
    }

    public static function allows(Event $event, string $day, string $start, string $end): bool
    {
        $now = now($event->timezone);

        return $day === $now->toDateString()
            || ($day === $now->copy()->subDay()->toDateString() && $end < $start && $now->format('H:i:s') <= $end);
    }
}
