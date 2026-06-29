<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodPreferenceRequest;
use App\Models\FoodPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FoodPreferenceController extends Controller
{
    public function index(): JsonResponse
    {
        $preferences = FoodPreference::all()->groupBy('type');

        return response()->json([
            'liked'    => $preferences->get('liked', collect())->pluck('food_name')->values(),
            'disliked' => $preferences->get('disliked', collect())->pluck('food_name')->values(),
        ]);
    }

    public function store(StoreFoodPreferenceRequest $request): JsonResponse
    {
        $foodName = $request->validated('food_name');
        $type     = $request->validated('type');
        $opposite = $type === 'liked' ? 'disliked' : 'liked';

        $existing = FoodPreference::where('food_name', $foodName)->where('type', $type)->first();

        if ($existing) {
            return response()->json(['message' => 'Déjà dans la liste'], 409);
        }

        FoodPreference::where('food_name', $foodName)->where('type', $opposite)->delete();

        $preference = FoodPreference::create(['food_name' => $foodName, 'type' => $type]);

        return response()->json(['data' => [
            'id'        => $preference->id,
            'food_name' => $preference->food_name,
            'type'      => $preference->type,
        ]], 201);
    }

    public function destroy(FoodPreference $preference): Response
    {
        $preference->delete();

        return response()->noContent();
    }
}
