<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\DestroyMealRequest;
use App\Http\Requests\Kitchen\IndexMealsRequest;
use App\Http\Requests\Kitchen\StoreMealRequest;
use App\Http\Requests\Kitchen\UpdateMealRequest;
use App\Http\Resources\MealDataTableResource;
use App\Http\Resources\MealResource;
use App\Http\Resources\MealTypeResource;
use App\Models\Event;
use App\Models\Meal;
use App\Repositories\MealRepository;
use App\Services\MealService;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    public function __construct(private readonly MealService $meals) {}

    public function index(IndexMealsRequest $request, EventContext $context, MealRepository $repository): MealDataTableResource
    {
        return new MealDataTableResource($repository->dataTable(
            $context->requireCurrent($request->user()), $request->validated(),
        ));
    }

    public function create(Request $request, EventContext $context): Response
    {
        return $this->editor($context->requireCurrent($request->user()));
    }

    public function edit(Request $request, Meal $meal, EventContext $context): Response
    {
        $event = $context->requireCurrent($request->user());
        abort_unless((int) $meal->event_id === (int) $event->id, 404);

        return $this->editor($event, $meal);
    }

    public function store(StoreMealRequest $request, Event $event): RedirectResponse
    {
        $this->meals->create($event, $request->validated());

        return redirect()->route('meals.settings')->with('success', __('meals.created'));
    }

    public function update(UpdateMealRequest $request, Event $event, Meal $meal): RedirectResponse
    {
        $this->meals->update($event, $meal, $request->validated());

        return redirect()->route('meals.settings')->with('success', __('meals.saved'));
    }

    public function destroy(DestroyMealRequest $request, Event $event, Meal $meal): RedirectResponse
    {
        $this->meals->destroy($event, $meal);

        return redirect()->route('meals.settings')->with('success', __('meals.deleted'));
    }

    private function editor(Event $event, ?Meal $meal = null): Response
    {
        return Inertia::render('Kitchen/MealEditor', [
            'event' => [
                'id' => $event->id, 'name' => $event->name,
                'starts_on' => $event->starts_on->format('Y-m-d'),
                'ends_on' => $event->ends_on->format('Y-m-d'),
                'timezone' => $event->timezone, 'is_locked' => $event->isLocked(),
            ],
            'meal' => $meal ? (new MealResource($meal->load('mealType')->loadExists('shiftMeals')))->resolve() : null,
            'mealTypes' => MealTypeResource::collection($event->mealTypes()->orderBy('starts_at')->orderBy('id')->get())->resolve(),
            'canWrite' => ! $event->isLocked() && ($meal === null || ! $meal->shift_meals_exists),
        ]);
    }
}
