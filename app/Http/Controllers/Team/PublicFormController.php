<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\EditTeamFormRequest;
use App\Http\Requests\Team\StorePublicTeamFormRequest;
use App\Models\TeamForm;
use App\Services\TeamFormPageData;
use App\Services\TeamFormService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function __construct(
        private readonly TeamFormService $forms,
        private readonly TeamFormPageData $pageData,
        private readonly EventContext $eventContext,
    ) {}

    public function show(string $slug): Response
    {
        $form = $this->findLiveForm($slug);

        return Inertia::render('Public/TeamForm', $this->pageData->for($form));
    }

    public function store(StorePublicTeamFormRequest $request, string $slug): RedirectResponse
    {
        $form = $request->teamForm();
        $this->forms->submit($form, $request->validated());
        $request->session()->put('confirmed_team_form', $form->id);

        return redirect()->route('team.forms.public.confirmation', $slug);
    }

    public function preview(EditTeamFormRequest $request, TeamForm $teamForm): Response
    {
        $teamForm->loadMissing('event');
        $this->eventContext->requireCurrentEvent($request->user(), $teamForm->event);

        return Inertia::render('Public/TeamForm', $this->pageData->for($teamForm, preview: true));
    }

    public function confirmation(Request $request, string $slug): Response
    {
        $form = TeamForm::query()->with('event')->where('slug', $slug)->firstOrFail();
        abort_unless($request->session()->pull('confirmed_team_form') === $form->id, 404);

        return Inertia::render('Public/TeamFormConfirmation', [
            'form' => ['name' => $form->name],
            'event' => ['name' => $form->event->name],
        ]);
    }

    private function findLiveForm(string $slug): TeamForm
    {
        return TeamForm::query()
            ->with(['event', 'fields.customField'])
            ->where('slug', $slug)
            ->where('status', 'live')
            ->firstOrFail();
    }
}
