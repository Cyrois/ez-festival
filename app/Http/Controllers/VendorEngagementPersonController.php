<?php

namespace App\Http\Controllers;

use App\Http\Requests\EngagementPeople\StoreEngagementPersonRequest;
use App\Http\Requests\EngagementPeople\UpdateEngagementPersonRequest;
use App\Models\Person;
use App\Models\VendorEngagement;
use App\Services\EngagementPersonService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class VendorEngagementPersonController extends Controller
{
    public function __construct(
        private readonly EventContext $eventContext,
        private readonly EngagementPersonService $people,
    ) {}

    public function store(StoreEngagementPersonRequest $request, VendorEngagement $engagement): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        $this->people->store($engagement, $request->validated());

        return back()->with('success', __('people.toast.created'));
    }

    public function update(UpdateEngagementPersonRequest $request, VendorEngagement $engagement, Person $person): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent($request->user(), $engagement->event, writable: true);
        $this->people->update($engagement, $person, $request->validated());

        return back()->with('success', __('people.toast.updated'));
    }

    public function destroy(VendorEngagement $engagement, Person $person): RedirectResponse
    {
        $this->eventContext->requireCurrentEvent(request()->user(), $engagement->event, writable: true);
        $this->people->destroy($engagement, $person);

        return back()->with('success', __('people.toast.removed'));
    }
}
