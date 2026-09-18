<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Models\CustomField;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $values = $user->customFieldValues()->get()->keyBy('custom_field_id');
        $fields = CustomField::query()
            ->where('organization_id', app(OrganizationContext::class)->organization()->id)
            ->where('target', CustomField::TARGET_USER)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Settings/Account', [
            'account' => $user->only(['name', 'email', 'phone']),
            'customFields' => $fields->map(fn (CustomField $field) => [
                'id' => $field->id,
                'label' => $field->label,
                'key' => $field->key,
                'type' => $field->type,
                'required' => $field->required,
                'options' => $field->options,
                'value' => $values->get($field->id)?->typedValue($field),
            ])->values(),
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($data, $request, $user): void {
            $user->update(Arr::except($data, 'custom_fields'));

            foreach ($request->customFields() as $field) {
                if (! array_key_exists($field->id, $data['custom_fields'] ?? [])) {
                    continue;
                }

                $value = data_get($data, "custom_fields.{$field->id}");

                if ($this->isEmptyValue($field, $value)) {
                    $user->customFieldValues()
                        ->where('custom_field_id', $field->id)
                        ->delete();

                    continue;
                }

                $fieldValue = $user->customFieldValues()->firstOrNew([
                    'custom_field_id' => $field->id,
                ]);
                $fieldValue->setTypedValue($field, $value);
                $fieldValue->save();
            }
        });

        return back();
    }

    private function isEmptyValue(CustomField $field, mixed $value): bool
    {
        return $field->type !== 'checkbox' && ($value === null || $value === '');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back();
    }
}
