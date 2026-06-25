<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
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
            $recipe = Recipe::create($request->safe()->except('ingredients'));
            $recipe->ingredients()->createMany($request->validated('ingredients'));

            return $recipe;
        });

        return response()->json(new RecipeResource($recipe->load('ingredients')), 201);
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

        return response()->json(new RecipeResource($recipe->fresh('ingredients')));
    }

    public function destroy(Recipe $recipe): Response
    {
        $recipe->delete();

        return response()->noContent();
    }
}
