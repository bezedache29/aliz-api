<?php

use App\Models\Profile;
use App\Models\WeightEntry;
use App\Services\NutritionGoalService;

function nutritionProfilePayload(): array
{
    return [
        'first_name'          => 'Christophe',
        'age'                 => 43,
        'gender'              => 'male',
        'height_cm'           => 178,
        'current_weight_kg'   => 82.5,
        'target_weight_kg'    => 75.0,
        'activity_level'      => 'moderate',
        'weight_loss_rate_kg' => 0.5,
    ];
}

it('returns null daily goals when no profile exists', function () {
    expect(app(NutritionGoalService::class)->dailyGoals())->toBeNull();
});

it('computes daily goals from the profile', function () {
    Profile::create(nutritionProfilePayload());

    $goals = app(NutritionGoalService::class)->dailyGoals();

    expect($goals)->toBe([
        'kcal'      => 2178.0,
        'proteines' => 165.0,
        'glucides'  => 242.0,
        'lipides'   => 61.0,
    ]);
});

it('uses the latest weight entry instead of the profile weight when available', function () {
    Profile::create(nutritionProfilePayload());
    WeightEntry::create(['weight' => 78, 'measured_at' => now()->subDay()]);
    WeightEntry::create(['weight' => 76, 'measured_at' => now()]);

    $goals = app(NutritionGoalService::class)->dailyGoals();

    // Avec 76kg (dernière pesée) au lieu de 82.5kg (profil), le kcal journalier doit être différent.
    expect($goals['kcal'])->not->toBe(2178.0);
});

it('splits the daily budget across meals proportionally', function () {
    Profile::create(nutritionProfilePayload());

    $mealGoals = app(NutritionGoalService::class)->mealGoals('Déjeuner');

    expect($mealGoals)->toBe([
        'kcal'      => 762.0,
        'proteines' => 58.0,
        'glucides'  => 85.0,
        'lipides'   => 21.0,
    ]);
});

it('returns null meal goals when no profile exists', function () {
    expect(app(NutritionGoalService::class)->mealGoals('Déjeuner'))->toBeNull();
});
