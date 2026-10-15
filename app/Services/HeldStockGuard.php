<?php

namespace App\Services;

use App\Models\TeamEngagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HeldStockGuard
{
    public function hasStock(int $personId, ?int $eventId = null): bool
    {
        $incoming = DB::table('stock_movements as m')->join('stock_holders as sh', 'sh.id', '=', 'm.to_holder_id')
            ->where('sh.person_id', $personId)->when($eventId !== null, fn ($q) => $q->where('sh.event_id', $eventId))
            ->select('sh.id as holder_id', 'm.entitlement_item_id')->selectRaw('CAST(m.quantity AS BIGINT) as delta');
        $outgoing = DB::table('stock_movements as m')->join('stock_holders as sh', 'sh.id', '=', 'm.from_holder_id')
            ->where('sh.person_id', $personId)->when($eventId !== null, fn ($q) => $q->where('sh.event_id', $eventId))
            ->select('sh.id as holder_id', 'm.entitlement_item_id')->selectRaw('-CAST(m.quantity AS BIGINT) as delta');

        return DB::query()->fromSub($incoming->unionAll($outgoing), 'entries')->select('holder_id', 'entitlement_item_id')
            ->groupBy('holder_id', 'entitlement_item_id')->havingRaw('SUM(delta) > 0')->exists();
    }

    public function ensureClear(int $personId, ?int $eventId, string $field): void
    {
        if ($this->hasStock($personId, $eventId)) {
            throw ValidationException::withMessages([$field => __('inventory.errors.stock_remains')]);
        }
    }

    public function teamChangeField(TeamEngagement $member, array $data): ?string
    {
        if ($member->status === 'hired' && isset($data['status']) && $data['status'] !== 'hired') {
            return 'status';
        }
        if ($member->role_id !== null && array_key_exists('role_id', $data) && $data['role_id'] === null) {
            return 'role_id';
        }
        if (isset($data['email']) && mb_strtolower(trim($data['email'])) !== $member->person->email) {
            return 'email';
        }

        return null;
    }
}
