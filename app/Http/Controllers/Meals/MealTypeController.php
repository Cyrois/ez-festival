<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\IndexMealTypesRequest;
use App\Http\Requests\Meals\StoreMealTypeRequest;
use App\Http\Requests\Meals\UpdateMealTypeRequest;
use App\Http\Resources\MealTypeDataTableResource;
use App\Models\Event;
use App\Models\MealType;
use App\Repositories\MealTypeRepository;
use App\Services\MealTypeService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;

class MealTypeController extends Controller
{
    public function __construct(private readonly MealTypeService $types) {}

    public function index(IndexMealTypesRequest $request, EventContext $context, MealTypeRepository $repository): MealTypeDataTableResource
    {
        return new MealTypeDataTableResource($repository->dataTable(
            $context->requireCurrent($request->user()), $request->validated(),
        ));
    }

    public function store(StoreMealTypeRequest $request, Event $event): RedirectResponse
    {
        $this->types->create($event, $request->validated());

        return redirect()->route('meals.settings')->with('success', __('meals.types.saved'));
    }

    public function update(UpdateMealTypeRequest $request, Event $event, MealType $mealType): RedirectResponse
    {
        $this->types->update($event, $mealType, $request->validated());

        return redirect()->route('meals.settings')->with('success', __('meals.types.saved'));
    }
}
