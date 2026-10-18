<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class EntitlementCode
{
    public static function key(string $code): string
    {
        return mb_strtolower(trim($code));
    }

    public static function isUsed(int $eventId, string $code): bool
    {
        return DB::table('entitlement_code_reservations')
            ->where('event_id', $eventId)->where('code_key', self::key($code))->exists();
    }

    public static function reserve(int $eventId, string $code): bool
    {
        return DB::table('entitlement_code_reservations')->insertOrIgnore([
            'event_id' => $eventId,
            'code_key' => self::key($code),
        ]) === 1;
    }
}
