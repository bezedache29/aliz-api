<?php

use App\Http\Controllers\FoodPreferenceController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.static')->group(function () {
    // Préférences alimentaires
    Route::get('preferences', [FoodPreferenceController::class, 'index']);
    Route::post('preferences', [FoodPreferenceController::class, 'store']);
    Route::delete('preferences/{preference}', [FoodPreferenceController::class, 'destroy']);

    // Recettes
    Route::post('recipes/generate', [RecipeController::class, 'generate']);
    Route::apiResource('recipes', RecipeController::class)->except(['show']);

    // Planning
    Route::get('planning/week', [PlanningController::class, 'week']);
    Route::post('planning/week/{dateKey}/meals/{mealType}/regenerate', [PlanningController::class, 'regenerate']);

    // Stock alimentaire
    Route::apiResource('stock', StockController::class)->except(['show']);

    // Poids & Suivi
    Route::get('weight', [WeightController::class, 'index']);
    Route::post('weight', [WeightController::class, 'store']);
    Route::post('weight/sync-renpho', [WeightController::class, 'syncRenpho']);
    Route::delete('weight/{weight}', [WeightController::class, 'destroy']);

    // Profil
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'store']);
    Route::put('profile', [ProfileController::class, 'update']);
});
