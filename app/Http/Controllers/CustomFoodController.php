<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomFoodRequest;
use App\Models\CustomFood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CustomFoodController extends Controller
{
    public function index(): JsonResponse
    {
        $foods = CustomFood::orderBy('name')->get();

        return response()->json(['data' => $foods]);
    }

    public function store(StoreCustomFoodRequest $request): JsonResponse
    {
        $food = CustomFood::create($request->validated());

        return response()->json(['data' => $food], 201);
    }

    public function destroy(CustomFood $customFood): Response
    {
        $customFood->delete();

        return response()->noContent();
    }
}
