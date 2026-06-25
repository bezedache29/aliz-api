<?php

use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.static')->group(function () {
    // Recettes
    Route::apiResource('recipes', RecipeController::class)->except(['show']);

    // Planning
    Route::get('planning/week', [PlanningController::class, 'week']);
    Route::post('planning/week/{dateKey}/meals/{mealType}/regenerate', [PlanningController::class, 'regenerate']);

    // Stock alimentaire
    Route::apiResource('stock', StockController::class)->except(['show']);

    // Poids & Suivi
    Route::get('weight', [WeightController::class, 'index']);
    Route::post('weight', [WeightController::class, 'store']);
    Route::delete('weight/{weight}', [WeightController::class, 'destroy']);
    Route::post('weight/sync-renpho', [WeightController::class, 'syncRenpho']);

    // Profil
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
});
