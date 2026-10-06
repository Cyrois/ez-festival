<?php

namespace App\Support;

final class MealWindow
{
    public static function overlaps(string $start, string $end, string $otherStart, string $otherEnd): bool
    {
        foreach (self::intervals($start, $end) as [$from, $to]) {
            foreach (self::intervals($otherStart, $otherEnd) as [$otherFrom, $otherTo]) {
                if ($from < $otherTo && $otherFrom < $to) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function intervals(string $start, string $end): array
    {
        $from = (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
        $to = (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);

        return $to > $from ? [[$from, $to]] : [[$from, 1440], [0, $to]];
    }
}
