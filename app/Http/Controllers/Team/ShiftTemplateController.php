<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\DestroyShiftTemplateRequest;
use App\Http\Requests\Team\StoreShiftTemplateRequest;
use App\Http\Requests\Team\UpdateShiftTemplateRequest;
use App\Models\Event;
use App\Models\ShiftTemplate;
use App\Services\ShiftTemplateService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class ShiftTemplateController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly ShiftTemplateService $shiftTemplates,
    ) {}

    public function store(StoreShiftTemplateRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $this->shiftTemplates->create($event, $request->validated());

        return $this->redirectWithSuccess('team.configure.templates.toast.created');
    }

    public function update(
        UpdateShiftTemplateRequest $request,
        Event $event,
        ShiftTemplate $shiftTemplate,
    ): RedirectResponse {
        $this->ensureCurrentTemplate($request->user(), $event, $shiftTemplate);
        $this->shiftTemplates->update($shiftTemplate, $request->validated());

        return $this->redirectWithSuccess('team.configure.templates.toast.updated');
    }

    public function destroy(
        DestroyShiftTemplateRequest $request,
        Event $event,
        ShiftTemplate $shiftTemplate,
    ): RedirectResponse {
        $this->ensureCurrentTemplate($request->user(), $event, $shiftTemplate);
        $this->shiftTemplates->destroy($shiftTemplate);

        return $this->redirectWithSuccess('team.configure.templates.toast.deleted');
    }

    private function ensureCurrentTemplate($user, Event $event, ShiftTemplate $template): void
    {
        $this->eventContext->requireCurrentEvent($user, $event, writable: true);
        abort_unless($template->event_id === $event->id, 404);
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('team.configure')
            ->with('success', __($message))
            ->with('success_title', __('toast.saved_title'));
    }
}
