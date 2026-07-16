<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegenerateMealRequest;
use App\Http\Requests\WeekPlanningRequest;
use App\Models\PlanningMeal;
use App\Models\Recipe;
use App\Services\LlmService;
use App\Services\MealContextService;
use App\Services\NutritionGoalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlanningController extends Controller
{
    public function week(WeekPlanningRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->validated()['from']);
        $to   = $from->copy()->addDays(6);

        $meals = PlanningMeal::with(['recipe.ingredients'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('recipe_id')
            ->orderBy('date')
            ->orderBy('meal_type')
            ->get()
            ->map(fn(PlanningMeal $meal) => [
                'date'      => $meal->date->toDateString(),
                'meal_type' => $meal->meal_type,
                'recipe'    => $this->formatRecipe($meal->recipe),
            ])
            ->values();

        return response()->json(['meals' => $meals]);
    }

    public function regenerate(RegenerateMealRequest $request, string $dateKey, string $mealType): JsonResponse
    {
        $validated = $request->validated();

        $recipes     = Recipe::select(['id', 'name', 'meal', 'category'])->get();
        $mealBudget  = app(NutritionGoalService::class)->mealGoals($validated['meal_type']);
        $mealContext = app(MealContextService::class);
        $stock       = $mealContext->stock();
        $preferences = $mealContext->foodPreferences();

        $suggestion = app(LlmService::class)->suggestRecipe(
            $validated['date_key'],
            $validated['meal_type'],
            $recipes,
            $validated['prompt'] ?? null,
            $mealBudget,
            $stock['expiring'],
            $stock['other'],
            $preferences['liked'],
            $preferences['disliked'],
        );

        try {
            $recipe = $this->resolveRecipe($suggestion, $mealType);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        PlanningMeal::updateOrCreate(
            ['date' => $dateKey, 'meal_type' => $mealType],
            ['recipe_id' => $recipe->id],
        );

        return response()->json(['recipe' => $this->formatRecipe($recipe)]);
    }

    private function resolveRecipe(array $suggestion, string $mealType): Recipe
    {
        if ($suggestion['type'] === 'existing') {
            if (empty($suggestion['recipe_id'])) {
                throw new \RuntimeException('Réponse LLM invalide : recipe_id manquant');
            }
            return Recipe::with('ingredients')->findOrFail($suggestion['recipe_id']);
        }

        if (empty($suggestion['name'])) {
            throw new \RuntimeException('Réponse LLM invalide : name manquant');
        }

        return DB::transaction(function () use ($suggestion, $mealType) {
            $recipe = Recipe::create([
                'name'                => $suggestion['name'],
                'description'         => $suggestion['description'] ?? null,
                'category'            => $this->categoryFromMealType($mealType),
                'meal'                => $mealType,
                'steps'               => $suggestion['steps'] ?? [],
                'seasons'             => [],
                'is_ai_generated'     => true,
                'kcal_estimated'      => $suggestion['kcal'] ?? null,
                'proteines_estimated' => $suggestion['proteines'] ?? null,
                'glucides_estimated'  => $suggestion['glucides'] ?? null,
                'lipides_estimated'   => $suggestion['lipides'] ?? null,
                'prep_time'           => $suggestion['prep_time'] ?? null,
                'cook_time'           => $suggestion['cook_time'] ?? null,
            ]);

            $recipe->ingredients()->createMany(
                collect($suggestion['ingredients'] ?? [])->map(fn ($i) => [
                    'food_name'         => $i['food_name'],
                    'quantity_g'        => $i['quantity_g'],
                    'per100g_kcal'      => $i['per100g_kcal'],
                    'per100g_proteines' => $i['per100g_proteines'],
                    'per100g_glucides'  => $i['per100g_glucides'],
                    'per100g_lipides'   => $i['per100g_lipides'],
                ])->all()
            );

            return $recipe;
        });
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
