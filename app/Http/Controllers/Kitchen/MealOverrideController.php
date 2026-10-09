<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\DestroyMealOverrideRequest;
use App\Http\Requests\Kitchen\IndexMealOverridesRequest;
use App\Http\Requests\Kitchen\StoreMealOverrideRequest;
use App\Http\Resources\MealClaimResultResource;
use App\Http\Resources\MealOverrideOptionResource;
use App\Models\Event;
use App\Models\TeamEngagement;
use App\Queries\MealEntitlementQuery;
use App\Services\MealOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MealOverrideController extends Controller
{
    public function index(IndexMealOverridesRequest $request, Event $event, TeamEngagement $member, MealEntitlementQuery $meals): AnonymousResourceCollection
    {
        return MealOverrideOptionResource::collection($meals->overrideOptions($event, $member->id));
    }

    public function store(StoreMealOverrideRequest $request, Event $event, TeamEngagement $member, MealOverrideService $overrides): JsonResponse
    {
        $result = $overrides->give($event, $member, $request->user(), (int) $request->validated('meal_id'));

        return (new MealClaimResultResource($result))->response()->setStatusCode($result['status'] === 'overridden' ? 201 : 409);
    }

    public function destroy(DestroyMealOverrideRequest $request, Event $event, TeamEngagement $member, MealOverrideService $overrides): JsonResponse
    {
        $result = $overrides->remove($event, $member, $request->user(), (int) $request->validated('assignment_id'));

        return (new MealClaimResultResource($result))->response()->setStatusCode($result['status'] === 'override_removed' ? 200 : 409);
    }
}
