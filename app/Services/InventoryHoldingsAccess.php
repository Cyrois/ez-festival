<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Queries\InventoryTargetsQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryHoldingsAccess
{
    public function supervisedShifts(Event $event, User $actor): Builder
    {
        return DB::table('shift_assignments as supervisor')
            ->join('team_engagements as supervisor_member', 'supervisor_member.id', '=', 'supervisor.team_engagement_id')
            ->join('people as supervisor_person', 'supervisor_person.id', '=', 'supervisor_member.person_id')
            ->join('users as supervisor_user', 'supervisor_user.person_id', '=', 'supervisor_person.id')
            ->join('roles as supervisor_role', 'supervisor_role.id', '=', 'supervisor_member.role_id')
            ->where('supervisor_user.id', $actor->id)->where('supervisor_person.can_log_in', true)->where('supervisor_role.active', true)
            ->join('shifts as supervised_shift', 'supervised_shift.id', '=', 'supervisor.shift_id')
            ->where('supervisor_member.event_id', $event->id)->where('supervised_shift.event_id', $event->id)
            ->where('supervisor_member.person_id', $actor->person_id)->where('supervisor.is_supervisor', true);
    }

    public function canView(Event $event, User $actor): bool
    {
        return $actor->can('inventory.manage', $event) || $this->supervisedShifts($event, $actor)->exists();
    }

    public function canAct(Event $event, User $actor, array $scope): bool
    {
        return in_array('inventory.manage', app(InventoryTargetsQuery::class)->permissions($event, $actor), true)
            || (! empty($scope['shift_id']) && $this->supervisedShifts($event, $actor)->where('supervisor.shift_id', $scope['shift_id'])->exists());
    }

    public function roster(Event $event, array $scope): Builder
    {
        $query = DB::table('team_engagements as member')->where('member.event_id', $event->id);
        if (! empty($scope['shift_id'])) {
            $query->join('shift_assignments as assignment', 'assignment.team_engagement_id', '=', 'member.id')
                ->join('shifts as selected_shift', 'selected_shift.id', '=', 'assignment.shift_id')
                ->where('selected_shift.event_id', $event->id)->where('assignment.shift_id', $scope['shift_id']);
        } else {
            $query->where('member.group_id', $scope['group_id'] ?? 0);
        }

        return $query;
    }

    public function authorizeScope(Event $event, User $actor, array $scope, bool $manager): void
    {
        abort_unless((! empty($scope['shift_id'])) !== (! empty($scope['group_id'])), 422);
        if ($manager) {
            $table = empty($scope['shift_id']) ? 'groups' : 'shifts';
            abort_unless(DB::table($table)->where('event_id', $event->id)->where('id', $scope['shift_id'] ?? $scope['group_id'])->exists(), 404);

            return;
        }
        abort_unless(! empty($scope['shift_id']) && empty($scope['group_id'])
            && $this->supervisedShifts($event, $actor)->where('supervisor.shift_id', $scope['shift_id'])->exists(), 403);
    }

    public function authorizePerson(Event $event, User $actor, array $scope, int $personId, bool $manager): void
    {
        $this->authorizeScope($event, $actor, $scope, $manager);
        abort_unless($this->roster($event, $scope)->where('member.person_id', $personId)->exists(), 403);
    }
}
