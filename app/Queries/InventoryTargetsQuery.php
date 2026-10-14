<?php

namespace App\Queries;

use App\Models\Event;
use App\Support\Permissions;
use App\Support\SqlLike;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryTargetsQuery
{
    /** Location pulls include the requesting manager; person transfers can exclude them. */
    public function receivers(Event $event, ?int $excludePersonId = null, string $search = ''): Builder
    {
        return $this->eligible($event, ['checkin.edit', 'inventory.manage'], $excludePersonId, $search);
    }

    public function managers(Event $event, int $excludePersonId, string $search = ''): Builder
    {
        return $this->eligible($event, ['inventory.manage'], $excludePersonId, $search);
    }

    public function canReceive(Event $event, int $personId): bool
    {
        return $this->receivers($event)->where('p.id', $personId)->exists();
    }

    /** Target eligibility only; the owning Return flow must authorize its actor separately. */
    public function isManagerTarget(Event $event, int $personId, int $actorPersonId): bool
    {
        return $this->managers($event, $actorPersonId)->where('p.id', $personId)->exists();
    }

    /** Return a query for server pagination and transactional target revalidation. */
    private function eligible(Event $event, array $permissions, ?int $excludePersonId, string $search): Builder
    {
        $grantingKeys = array_values(array_filter(Permissions::keys(), fn (string $key): bool => array_intersect(Permissions::expand([$key]), $permissions) !== []));

        return DB::table('people as p')
            ->join('users as u', 'u.person_id', '=', 'p.id')
            ->where('p.can_log_in', true)
            ->where(function (Builder $query) use ($event, $grantingKeys): void {
                $query->where('u.is_admin', true)
                    ->orWhereExists(function (Builder $members) use ($event, $grantingKeys): void {
                        $members->selectRaw('1')->from('team_engagements as te')
                            ->join('roles as r', 'r.id', '=', 'te.role_id')
                            ->whereColumn('te.person_id', 'p.id')
                            ->where('te.event_id', $event->id)->where('te.status', 'hired')
                            ->where('r.active', true)
                            ->where(function (Builder $roles) use ($grantingKeys): void {
                                foreach ($grantingKeys as $key) {
                                    $roles->orWhereJsonContains('r.permissions', $key);
                                }
                            });
                    });
            })
            ->when($excludePersonId !== null, fn (Builder $query) => $query->where('p.id', '!=', $excludePersonId))
            ->when(trim($search) !== '', fn (Builder $query) => $query->whereRaw(
                "LOWER(p.name) LIKE ? ESCAPE '!'", ['%'.SqlLike::escape(mb_strtolower(trim($search))).'%'],
            ))
            ->select('p.id', 'p.name')->orderByRaw('LOWER(p.name)')->orderBy('p.id');
    }
}
