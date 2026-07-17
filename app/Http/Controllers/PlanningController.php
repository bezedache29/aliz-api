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
use Illuminate\Support\Str;

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
            ->orderByRaw("FIELD(course, 'Entrée', 'Plat', 'Dessert', '')")
            ->get()
            ->groupBy(fn (PlanningMeal $meal) => $meal->date->toDateString() . '|' . $meal->meal_type)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'date'      => $first->date->toDateString(),
                    'meal_type' => $first->meal_type,
                    'courses'   => $group->map(fn (PlanningMeal $m) => [
                        'course' => $m->course,
                        'recipe' => $this->formatRecipe($m->recipe),
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['meals' => $meals]);
    }

    public function regenerate(RegenerateMealRequest $request, string $dateKey, string $mealType): JsonResponse
    {
        $validated = $request->validated();

        $recipes      = Recipe::select(['id', 'name', 'meal', 'category'])->get();
        $mealBudget   = app(NutritionGoalService::class)->mealGoals($validated['meal_type']);
        $mealContext  = app(MealContextService::class);
        $stock        = $mealContext->stock();
        $preferences  = $mealContext->foodPreferences();
        $plannedToday = $this->plannedMealsForDay($validated['date_key'], $validated['meal_type']);

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
            $plannedToday,
        );

        $courseSuggestions = $suggestion['type'] === 'menu'
            ? $suggestion['courses']
            : [array_merge(['course' => ''], $suggestion)];

        try {
            $resolved = collect($courseSuggestions)
                ->map(fn (array $c) => [
                    'course' => $c['course'],
                    'recipe' => $this->resolveRecipe($c, $mealType),
                ])
                ->values();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        DB::transaction(function () use ($dateKey, $mealType, $resolved) {
            PlanningMeal::where('date', $dateKey)->where('meal_type', $mealType)->delete();

            foreach ($resolved as $entry) {
                PlanningMeal::create([
                    'date'      => $dateKey,
                    'meal_type' => $mealType,
                    'course'    => $entry['course'],
                    'recipe_id' => $entry['recipe']->id,
                ]);
            }
        });

        return response()->json([
            'courses' => $resolved->map(fn ($entry) => [
                'course' => $entry['course'],
                'recipe' => $this->formatRecipe($entry['recipe']),
            ])->values(),
        ]);
    }

    private function plannedMealsForDay(string $dateKey, string $excludingMealType): array
    {
        return PlanningMeal::with('recipe.ingredients')
            ->where('date', $dateKey)
            ->where('meal_type', '!=', $excludingMealType)
            ->get()
            ->filter(fn (PlanningMeal $m) => $m->recipe !== null)
            ->groupBy('meal_type')
            ->map(fn ($group, $mealType) => [
                'meal_type'   => $mealType,
                'name'        => $group->map(fn (PlanningMeal $m) => $m->recipe->name)->join(' + '),
                'ingredients' => $group
                    ->flatMap(fn (PlanningMeal $m) => $m->recipe->ingredients->pluck('food_name'))
                    ->unique()->values()->all(),
            ])
            ->values()->all();
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

        $course = $suggestion['course'] ?? '';

        return DB::transaction(function () use ($suggestion, $mealType, $course) {
            $recipe = Recipe::create([
                'name'                => $suggestion['name'],
                'description'         => $suggestion['description'] ?? null,
                'category'            => $this->categoryFor($course, $mealType),
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
                    'food_id'           => $i['food_id'] ?? Str::uuid()->toString(),
                    'food_name'         => $i['food_name'],
                    'food_source'       => $i['food_source'] ?? 'manual',
                    'quantity_g'        => $i['quantity_g'],
                    'per100g_kcal'      => $i['per100g_kcal'],
                    'per100g_proteines' => $i['per100g_proteines'],
                    'per100g_glucides'  => $i['per100g_glucides'],
                    'per100g_lipides'   => $i['per100g_lipides'],
                    'per100g_fibres'    => $i['per100g_fibres'] ?? 0,
                    'per100g_sel'       => $i['per100g_sel'] ?? 0,
                ])->all()
            );

            return $recipe->load('ingredients');
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

    private function categoryFor(string $course, string $mealType): string
    {
        return match ($course) {
            'Entrée'  => 'Entrée',
            'Dessert' => 'Dessert',
            'Plat'    => 'Plat principal',
            default   => $this->categoryFromMealType($mealType),
        };
    }

    private function formatRecipe(Recipe $recipe): array
    {
        $macros = $recipe->macros();
        $recipe->loadMissing('ingredients');

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
            'steps'       => $recipe->steps,
            'ingredients' => $recipe->ingredients->map(fn ($i) => [
                'food_id'           => $i->food_id,
                'food_name'         => $i->food_name,
                'food_source'       => $i->food_source,
                'food_brand'        => $i->food_brand,
                'food_barcode'      => $i->food_barcode,
                'per100g_kcal'      => $i->per100g_kcal,
                'per100g_proteines' => $i->per100g_proteines,
                'per100g_glucides'  => $i->per100g_glucides,
                'per100g_lipides'   => $i->per100g_lipides,
                'per100g_fibres'    => $i->per100g_fibres,
                'per100g_sel'       => $i->per100g_sel,
                'quantity_g'        => $i->quantity_g,
            ])->all(),
        ];
    }
}
