<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegenerateMealRequest;
use App\Http\Requests\WeekPlanningRequest;
use App\Models\PlanningMeal;
use App\Models\Recipe;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;

class PlanningController extends Controller
{
    public function week(WeekPlanningRequest $request): JsonResponse
    {
        $date = $request->validated()['from'];

        $meals = PlanningMeal::with(['recipe.ingredients'])
            ->where('date', $date)
            ->whereNotNull('recipe_id')
            ->get()
            ->map(fn(PlanningMeal $meal) => [
                'meal_type' => $meal->meal_type,
                'recipe'    => $this->formatRecipe($meal->recipe),
            ])
            ->values();

        return response()->json(['meals' => $meals]);
    }

    public function regenerate(RegenerateMealRequest $request, string $dateKey, string $mealType): JsonResponse
    {
        $validated = $request->validated();

        $recipes = Recipe::with('ingredients')->get();

        $suggestion = app(LlmService::class)->suggestRecipe(
            $validated['date_key'],
            $validated['meal_type'],
            $recipes,
            $validated['prompt'] ?? null,
        );

        $recipe = $this->resolveRecipe($suggestion, $mealType);

        PlanningMeal::updateOrCreate(
            ['date' => $dateKey, 'meal_type' => $mealType],
            ['recipe_id' => $recipe->id],
        );

        return response()->json(['recipe' => $this->formatRecipe($recipe)]);
    }

    private function resolveRecipe(array $suggestion, string $mealType): Recipe
    {
        if ($suggestion['type'] === 'existing') {
            return Recipe::with('ingredients')->findOrFail($suggestion['recipe_id']);
        }

        return Recipe::create([
            'name'                => $suggestion['name'],
            'description'         => $suggestion['description'] ?? null,
            'category'            => $this->categoryFromMealType($mealType),
            'meal'                => $mealType,
            'steps'               => [],
            'seasons'             => [],
            'kcal_estimated'      => $suggestion['kcal'] ?? null,
            'proteines_estimated' => $suggestion['proteines'] ?? null,
            'glucides_estimated'  => $suggestion['glucides'] ?? null,
            'lipides_estimated'   => $suggestion['lipides'] ?? null,
            'prep_time'           => $suggestion['prep_time'] ?? null,
            'cook_time'           => $suggestion['cook_time'] ?? null,
        ]);
    }

    private function categoryFromMealType(string $mealType): string
    {
        return match ($mealType) {
            'Petit-déjeuner' => 'Petit-déjeuner',
            'Collation'      => 'Encas',
            default          => 'Plat principal',
        };
    }

    private function formatRecipe(Recipe $recipe): array
    {
        $macros = $recipe->macros();

        return [
            'id'          => $recipe->id,
            'name'        => $recipe->name,
            'kcal'        => $macros['kcal'],
            'proteines'   => $macros['proteines'],
            'glucides'    => $macros['glucides'],
            'lipides'     => $macros['lipides'],
            'prep_time'   => $recipe->prep_time,
            'cook_time'   => $recipe->cook_time,
            'description' => $recipe->description,
        ];
    }
}
