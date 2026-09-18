<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCustomFieldRequest;
use App\Http\Requests\Settings\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/CustomFields', [
            'fields' => $this->fields()->get([
                'id', 'target', 'label', 'key', 'type', 'required', 'options', 'active', 'sort_order',
            ]),
        ]);
    }

    public function store(StoreCustomFieldRequest $request): RedirectResponse
    {
        $organizationId = app(OrganizationContext::class)->organization()->id;
        $data = $request->validated();

        CustomField::query()->create([
            'organization_id' => $organizationId,
            'target' => $data['target'],
            'label' => $data['label'],
            'key' => $this->nextKey($data['label'], $data['target']),
            'type' => $data['type'],
            'required' => $data['required'] ?? false,
            'options' => $this->optionsFor($data),
            'sort_order' => ((int) $this->fields($data['target'])->max('sort_order')) + 1,
        ]);

        return back();
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField): RedirectResponse
    {
        $this->ensureOrganizationField($customField);
        $data = $request->validated();

        $customField->update([
            'label' => $data['label'],
            'type' => $data['type'],
            'required' => $data['required'] ?? $customField->required,
            'active' => $data['active'] ?? true,
            'options' => $this->optionsFor($data),
        ]);

        return back();
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $this->ensureOrganizationField($customField);
        $customField->delete();

        return back();
    }

    private function fields(?string $target = null)
    {
        $fields = CustomField::query()
            ->where('organization_id', app(OrganizationContext::class)->organization()->id);

        if ($target !== null) {
            $fields->where('target', $target);
        }

        return $fields
            ->orderBy('target')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function optionsFor(array $data): ?array
    {
        if ($data['type'] !== 'select') {
            return null;
        }

        return array_values($data['options'] ?? []);
    }

    private function nextKey(string $label, string $target): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $suffix = 2;

        while ($this->fields($target)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private function ensureOrganizationField(CustomField $customField): void
    {
        abort_unless(
            $customField->organization_id === app(OrganizationContext::class)->organization()->id,
            404,
        );
    }
}
