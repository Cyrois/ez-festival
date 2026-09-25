<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateTeamFormRequest;
use App\Http\Requests\Team\EditTeamFormRequest;
use App\Http\Requests\Team\IndexTeamFormsRequest;
use App\Http\Requests\Team\StoreTeamFormRequest;
use App\Http\Requests\Team\UpdateTeamFormRequest;
use App\Models\Event;
use App\Models\TeamForm;
use App\Models\TeamFormField;
use App\Services\TeamFormService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormsController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly TeamFormService $forms,
    ) {}

    public function index(IndexTeamFormsRequest $request): Response
    {
        $event = $this->eventContext->current($request->user());
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? null;
        $forms = TeamForm::query()
            ->when($event, fn ($query) => $query->whereBelongsTo($event), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TeamForm $form): array => $this->summary($form));

        return Inertia::render('Team/Forms', [
            'forms' => $forms,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => TeamForm::STATUSES,
            'event' => $event?->only('id', 'name', 'locked'),
            'canWrite' => $event !== null && ! $event->isLocked() && Gate::allows('manage-team'),
        ]);
    }

    public function create(CreateTeamFormRequest $request): Response
    {
        $event = $this->eventContext->requireWritable($request->user());

        return Inertia::render('Team/FormEditor', [
            'event' => $event->only('id', 'name'),
            'teamForm' => null,
            'statuses' => TeamForm::STATUSES,
            'initialFields' => $this->initialFields(),
        ]);
    }

    public function store(StoreTeamFormRequest $request, Event $event): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $form = $this->forms->create($event, $request->validated());

        return redirect()->route('team.forms.edit', $form)
            ->with('success', __('team.forms.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function edit(EditTeamFormRequest $request, TeamForm $teamForm): Response
    {
        $event = $this->resolveEvent($request, $teamForm);
        $teamForm->load('fields.customField');

        return Inertia::render('Team/FormEditor', [
            'event' => $event->only('id', 'name'),
            'teamForm' => [
                'id' => $teamForm->id,
                'name' => $teamForm->name,
                'slug' => $teamForm->slug,
                'status' => $teamForm->status,
                'public_url' => route('team.forms.public.show', $teamForm->slug),
                'preview_url' => route('team.forms.preview', $teamForm),
                'fields' => $teamForm->fields->map(fn (TeamFormField $field): array => $this->field($field))->values(),
            ],
            'statuses' => TeamForm::STATUSES,
            'initialFields' => $this->initialFields(),
        ]);
    }

    public function update(UpdateTeamFormRequest $request, TeamForm $teamForm): RedirectResponse
    {
        $this->resolveEvent($request, $teamForm, writable: true);
        $this->forms->update($teamForm, $request->validated());

        return redirect()->route('team.forms.edit', $teamForm)
            ->with('success', __('team.forms.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    private function resolveEvent(
        EditTeamFormRequest|UpdateTeamFormRequest $request,
        TeamForm $form,
        bool $writable = false,
    ): Event {
        $form->loadMissing('event');

        return $this->eventContext->requireCurrentEvent(
            $request->user(),
            $form->event,
            writable: $writable,
        );
    }

    /** @return array<string, mixed> */
    private function summary(TeamForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'status' => $form->status,
            'public_url' => route('team.forms.public.show', $form->slug),
            'preview_url' => route('team.forms.preview', $form),
            'edit_url' => route('team.forms.edit', $form),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function initialFields(): array
    {
        return [
            ['id' => null, 'key' => 'name', 'label' => __('team.forms.fields.name'), 'type' => 'text', 'required' => true, 'options' => []],
            ['id' => null, 'key' => 'email', 'label' => __('team.forms.fields.email'), 'type' => 'email', 'required' => true, 'options' => []],
            ['id' => null, 'key' => 'phone', 'label' => __('team.forms.fields.phone'), 'type' => 'phone', 'required' => false, 'options' => []],
            ['id' => null, 'key' => 'employment_type', 'label' => __('team.forms.fields.employment_type'), 'type' => 'select', 'required' => true, 'options' => ['volunteer', 'paid']],
        ];
    }

    /** @return array<string, mixed> */
    private function field(TeamFormField $field): array
    {
        if ($field->customField !== null) {
            return [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->customField->label,
                'type' => $field->customField->type,
                'required' => $field->required,
                'options' => $field->customField->options ?? [],
            ];
        }

        $initial = collect($this->initialFields())->firstWhere('key', $field->key);

        return [
            ...$initial,
            'id' => $field->id,
            'required' => in_array($field->key, [TeamFormField::KEY_NAME, TeamFormField::KEY_EMAIL], true)
                || $field->required,
        ];
    }
}
