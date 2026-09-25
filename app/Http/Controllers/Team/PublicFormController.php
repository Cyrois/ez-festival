<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StorePublicTeamFormRequest;
use App\Models\TeamForm;
use App\Models\TeamFormField;
use App\Services\TeamFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function __construct(private readonly TeamFormService $forms) {}

    public function show(string $slug): Response
    {
        $form = $this->findLiveForm($slug);

        return Inertia::render('Public/TeamForm', $this->formProps($form));
    }

    public function store(StorePublicTeamFormRequest $request, string $slug): RedirectResponse
    {
        $form = $request->teamForm();
        $this->forms->submit($form, $request->validated());
        $request->session()->put('confirmed_team_form', $form->id);

        return redirect()->route('team.forms.public.confirmation', $slug);
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

    /** @return array<string, mixed> */
    private function formProps(TeamForm $form): array
    {
        return [
            'form' => [
                'name' => $form->name,
                'action' => route('team.forms.public.store', $form->slug),
                'fields' => $form->fields->map(function (TeamFormField $field): array {
                    if ($field->customField !== null) {
                        return [
                            'key' => "custom_fields.{$field->custom_field_id}",
                            'label' => $field->customField->label,
                            'type' => $field->customField->type,
                            'required' => $field->required,
                            'options' => $field->customField->options ?? [],
                        ];
                    }

                    return [
                        'key' => $field->key,
                        'label' => __("team.forms.fields.{$field->key}"),
                        'type' => $field->key === 'employment_type' ? 'select' : $field->key,
                        'required' => $field->required,
                        'options' => $field->key === 'employment_type' ? ['volunteer', 'paid'] : [],
                    ];
                })->values(),
            ],
            'event' => ['name' => $form->event->name],
        ];
    }
}
