<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateRecipeRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Services\LlmService;
use App\Services\MealContextService;
use App\Services\NutritionGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index(): JsonResponse
    {
        $recipes = Recipe::with('ingredients')->get();

        return response()->json(['recipes' => RecipeResource::collection($recipes)]);
    }

    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $recipe = DB::transaction(function () use ($request) {
            $attributes = $request->safe()->except('ingredients');
            $attributes['is_ai_generated'] = $attributes['is_ai_generated'] ?? false;

            $recipe = Recipe::create($attributes);
            $recipe->ingredients()->createMany($request->validated('ingredients'));

            return $recipe;
        });

        return response()->json(['data' => new RecipeResource($recipe->load('ingredients'))], 201);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): JsonResponse
    {
        DB::transaction(function () use ($request, $recipe) {
            $recipe->update($request->safe()->except('ingredients'));

            if ($request->has('ingredients')) {
                $recipe->ingredients()->delete();
                $recipe->ingredients()->createMany($request->validated('ingredients'));
            }
        });

        return response()->json(['data' => new RecipeResource($recipe->load('ingredients'))]);
    }

    public function destroy(Recipe $recipe): Response
    {
        $recipe->delete();

        return response()->noContent();
    }

    public function generate(GenerateRecipeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $mealContext = app(MealContextService::class);

        $stock       = $mealContext->stock($validated['use_stock'] ?? true);
        $preferences = $mealContext->foodPreferences();

        $profileContext = app(NutritionGoalService::class)->dailyGoals();

        try {
            $data = app(LlmService::class)->generateFullRecipe(
                $validated['prompt'],
                $stock['expiring'],
                $stock['other'],
                $preferences['liked'],
                $preferences['disliked'],
                $profileContext,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $save = $validated['save'] ?? true;

        if (!$save) {
            return response()->json($data);
        }

        $recipe = DB::transaction(function () use ($data) {
            $recipe = Recipe::create([
                'name'                => $data['name'],
                'description'         => $data['description'] ?? null,
                'category'            => $data['category'],
                'meal'                => $data['meal'] ?? null,
                'cooking_method'      => $data['cooking_method'] ?? null,
                'seasons'             => $data['seasons'] ?? [],
                'prep_time'           => $data['prep_time'] ?? null,
                'cook_time'           => $data['cook_time'] ?? null,
                'is_ai_generated'     => true,
                'steps'               => $data['steps'],
                'kcal_estimated'      => $data['kcal_estimated'] ?? null,
                'proteines_estimated' => $data['proteines_estimated'] ?? null,
                'glucides_estimated'  => $data['glucides_estimated'] ?? null,
                'lipides_estimated'   => $data['lipides_estimated'] ?? null,
            ]);

            $recipe->ingredients()->createMany(
                collect($data['ingredients'])->map(fn ($i) => [
                    'food_name'         => $i['food_name'],
                    'quantity_g'        => $i['quantity_g'],
                    'per100g_kcal'      => $i['per100g_kcal'],
                    'per100g_proteines' => $i['per100g_proteines'],
                    'per100g_glucides'  => $i['per100g_glucides'],
                    'per100g_lipides'   => $i['per100g_lipides'],
                    'per100g_fibres'    => $i['per100g_fibres'] ?? null,
                    'per100g_sel'       => $i['per100g_sel'] ?? null,
                ])->all()
            );

            return $recipe;
        });

        return (new RecipeResource($recipe->load('ingredients')))
            ->response()
            ->setStatusCode(201);
    }
}
