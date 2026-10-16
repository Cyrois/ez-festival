<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SqlLike;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryTargetsQuery
{
    public const SHIFT_GRACE_MINUTES = 30;

    /** Fresh eligibility for writes as well as picker queries; never use request-cached grants here. */
    public function permissions(Event $event, User $actor): array
    {
        $user = User::with('person')->find($actor->id);
        if ($user === null || $user->person_id !== $actor->person_id || ! $user->person?->can_log_in) {
            return [];
        }
        if ($user->is_admin) {
            return Permissions::keys();
        }
        $member = TeamEngagement::with('role')->where('event_id', $event->id)->where('person_id', $user->person_id)->first();

        return $member?->status === 'hired' && $member->role?->active
            ? Permissions::expand($member->role->permissions ?? []) : [];
    }

    public function passReceivers(Event $event, User $actor, string $search = ''): Builder
    {
        $query = $this->receivers($event, $actor->person_id, $search);
        $permissions = $this->permissions($event, $actor);
        if (in_array('inventory.manage', $permissions, true)) {
            return $query->selectRaw('0 as on_shift, 0 as in_group');
        }
        $byShift = in_array('inventory.pass_shift', $permissions, true);
        $byGroup = in_array('inventory.pass_group', $permissions, true);
        $member = TeamEngagement::where('event_id', $event->id)->where('person_id', $actor->person_id)->first();
        if ($member === null || (! $byShift && ! $byGroup)) {
            return $query->selectRaw('0 as on_shift, 0 as in_group')->whereRaw('1 = 0');
        }
        // Shift datetimes are event-local wall clocks. Calvin confirmed parent-shift hours,
        // including both grace boundaries, regardless of either person's assignment hours.
        $now = CarbonImmutable::now($event->timezone);
        $latestStart = $now->addMinutes(self::SHIFT_GRACE_MINUTES)->format('Y-m-d H:i:s');
        $earliestEnd = $now->subMinutes(self::SHIFT_GRACE_MINUTES)->format('Y-m-d H:i:s');

        $shared = DB::table('shift_assignments as recipient_assignment')->selectRaw('1')
            ->join('team_engagements as recipient_member', 'recipient_member.id', '=', 'recipient_assignment.team_engagement_id')
            ->join('shifts as shared_shift', 'shared_shift.id', '=', 'recipient_assignment.shift_id')
            ->join('shift_assignments as giver_assignment', 'giver_assignment.shift_id', '=', 'shared_shift.id')
            ->whereColumn('recipient_member.person_id', 'p.id')
            ->where('recipient_member.event_id', $event->id)->where('shared_shift.event_id', $event->id)
            ->where('giver_assignment.team_engagement_id', $member->id)
            ->where('shared_shift.starts_at', '<=', $latestStart)->where('shared_shift.ends_at', '>=', $earliestEnd);
        $group = DB::table('team_engagements as group_member')->selectRaw('1')
            ->whereColumn('group_member.person_id', 'p.id')->where('group_member.event_id', $event->id)
            ->where('group_member.group_id', $member->group_id);
        $byGroup = $byGroup && $member->group_id !== null;
        $matches = DB::raw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END');
        // Reuse the eligibility predicates for the Designer's shift/group headings,
        // including across remote pages; never authorize or query each person in a loop.
        if ($byShift) {
            $query->selectSub((clone $shared)->select($matches), 'on_shift');
        } else {
            $query->selectRaw('0 as on_shift');
        }
        if ($byGroup) {
            $query->selectSub((clone $group)->select($matches), 'in_group');
        } else {
            $query->selectRaw('0 as in_group');
        }

        return $query->where(function (Builder $allowed) use ($byShift, $byGroup, $shared, $group): void {
            $allowed->whereRaw('1 = 0');
            if ($byShift) {
                $allowed->orWhereExists($shared);
            }
            if ($byGroup) {
                $allowed->orWhereExists($group);
            }
        })->reorder()->orderByDesc('on_shift')->orderByDesc('in_group')->orderByRaw('LOWER(p.name)')->orderBy('p.id');
    }

    /** One paginated, deduplicated people set for the combined Move stock picker. */
    public function moveReceivers(Event $event, User $actor, string $search = ''): Builder
    {
        $managers = $this->managers($event, $actor->person_id, $search)
            ->selectRaw('0 as on_shift, 0 as in_group, 1 as is_manager')->reorder();
        $people = $this->passReceivers($event, $actor, $search)
            ->whereNotIn('p.id', $this->managers($event, $actor->person_id)->reorder()->select('p.id'))
            ->selectRaw('0 as is_manager')->reorder();

        return DB::query()->fromSub($managers->unionAll($people), 'destinations')
            ->orderByDesc('is_manager')->orderByDesc('on_shift')->orderByDesc('in_group')
            ->orderByRaw('LOWER(name)')->orderBy('id');
    }

    public function canPassTo(Event $event, User $actor, int $personId): bool
    {
        return $this->passReceivers($event, $actor)->where('p.id', $personId)->exists();
    }

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
