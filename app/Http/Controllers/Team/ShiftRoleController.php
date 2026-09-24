<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\DestroyShiftRoleRequest;
use App\Http\Requests\Team\StoreShiftRoleRequest;
use App\Http\Requests\Team\UpdateShiftRoleRequest;
use App\Models\Event;
use App\Models\ShiftRole;
use App\Services\ShiftRoleService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class ShiftRoleController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly ShiftRoleService $shiftRoles,
    ) {}

    public function store(StoreShiftRoleRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->shiftRoles->create($event, $request->validated());

        return $this->redirectWithSuccess('team.configure.shift_roles.toast.created');
    }

    public function update(UpdateShiftRoleRequest $request, Event $event, ShiftRole $shiftRole): RedirectResponse
    {
        $this->ensureCurrentRole($request->user(), $event, $shiftRole);
        $this->shiftRoles->update($shiftRole, $request->validated());

        return $this->redirectWithSuccess('team.configure.shift_roles.toast.updated');
    }

    public function destroy(DestroyShiftRoleRequest $request, Event $event, ShiftRole $shiftRole): RedirectResponse
    {
        $this->ensureCurrentRole($request->user(), $event, $shiftRole);
        $this->shiftRoles->destroy($shiftRole);

        return $this->redirectWithSuccess('team.configure.shift_roles.toast.deleted');
    }

    private function ensureCurrentRole($user, Event $event, ShiftRole $shiftRole): void
    {
        $this->eventContext->requireCurrentEvent($user, $event, writable: true);
        abort_unless($shiftRole->event_id === $event->id, 404);
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('team.configure')
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
