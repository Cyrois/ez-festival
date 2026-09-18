<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Models\CustomField;
use App\Services\AccountService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
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

    public function update(UpdateAccountRequest $request, AccountService $accountService): RedirectResponse
    {
        $accountService->update(
            $request->user(),
            $request->validated(),
            $request->customFields(),
        );

        return back();
    }

    public function updatePassword(UpdatePasswordRequest $request, AccountService $accountService): RedirectResponse
    {
        $accountService->updatePassword(
            $request->user(),
            $request->validated('password'),
        );

        return back();
    }
}
