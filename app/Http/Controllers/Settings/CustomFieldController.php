<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCustomFieldRequest;
use App\Http\Requests\Settings\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    public function index(CustomFieldService $customFieldService): Response
    {
        return Inertia::render('Settings/CustomFields', [
            'fields' => $customFieldService->fields()->get([
                'id', 'target', 'label', 'key', 'type', 'required', 'options', 'active', 'sort_order',
            ]),
        ]);
    }

    public function store(StoreCustomFieldRequest $request, CustomFieldService $customFieldService): RedirectResponse
    {
        $customFieldService->create($request->validated());

        return back();
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField, CustomFieldService $customFieldService): RedirectResponse
    {
        $customFieldService->update($customField, $request->validated());

        return back();
    }

    public function destroy(CustomField $customField, CustomFieldService $customFieldService): RedirectResponse
    {
        $customFieldService->delete($customField);

        return back();
    }
}
