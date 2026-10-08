<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\IndexKitchenPeopleRequest;
use App\Http\Requests\Kitchen\ShowKitchenPersonRequest;
use App\Http\Resources\KitchenPeopleResource;
use App\Http\Resources\KitchenPersonResource;
use App\Models\TeamEngagement;
use App\Queries\KitchenPeopleQuery;
use App\Queries\MealEntitlementQuery;
use App\Support\EventContext;

class KitchenPersonController extends Controller
{
    public function index(IndexKitchenPeopleRequest $request, EventContext $context, KitchenPeopleQuery $people): KitchenPeopleResource
    {
        $event = $context->requireCurrent($request->user());
        $data = $request->validated();
        $search = trim($data['search'] ?? '');

        return new KitchenPeopleResource($search === ''
            ? ['people' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'matched_id' => null]
            : $people->search($event, $search, (int) ($data['page'] ?? 1)));
    }

    public function show(ShowKitchenPersonRequest $request, TeamEngagement $member, EventContext $context, MealEntitlementQuery $meals): KitchenPersonResource
    {
        $event = $context->requireCurrent($request->user());
        abort_unless((int) $member->event_id === (int) $event->id && $member->status === 'hired', 404);
        $data = $request->validated();
        $rows = $meals->forPerson($event, $member->id);

        return new KitchenPersonResource([
            'draw' => (int) $data['draw'], 'total' => $rows->count(),
            'rows' => $rows->orderBy('meal_date')->orderBy('starts_at')->orderBy('shift_location_name')
                ->orderBy('shift_starts_at')->orderBy('source_shift_id')->orderBy('meal_id')
                ->offset((int) $data['start'])->limit((int) $data['length'])->get(),
            'counts' => $meals->personCounts($event, $member->id), 'timezone' => $event->timezone,
            'person' => ['id' => $member->id, 'name' => $member->person->name,
                'type' => __('team.advancement.employment_type.'.$member->employment_type),
                'status' => __('team.advancement.status.'.$member->status)],
            'can_claim' => ! $event->isLocked() && $request->user()->can('meals.claim', $event), 'is_locked' => $event->isLocked(),
        ]);
    }
}
