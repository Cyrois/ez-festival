<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\ClaimMealRequest;
use App\Http\Requests\Kitchen\UnclaimMealRequest;
use App\Http\Resources\MealClaimResultResource;
use App\Models\Event;
use App\Models\TeamEngagement;
use App\Services\MealClaimService;
use Illuminate\Http\JsonResponse;

class MealClaimController extends Controller
{
    public function destroy(UnclaimMealRequest $request, Event $event, TeamEngagement $member, MealClaimService $claims): JsonResponse
    {
        $data = $request->validated();
        $result = $claims->unclaim($event, $member, $request->user(), (int) $data['claim_id']);

        return (new MealClaimResultResource($result))->response()->setStatusCode($result['status'] === 'unclaimed' ? 200 : 409);
    }

    public function store(ClaimMealRequest $request, Event $event, TeamEngagement $member, MealClaimService $claims): JsonResponse
    {
        $data = $request->validated();
        $result = $claims->claim($event, $member, $request->user(), (int) $data['meal_id'], (int) $data['source_shift_id'], (bool) ($data['confirm_warning'] ?? false));

        return (new MealClaimResultResource($result))->response()->setStatusCode($result['status'] === 'claimed' ? 201 : 409);
    }
}
