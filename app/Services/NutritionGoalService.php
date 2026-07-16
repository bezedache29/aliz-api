<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\WeightEntry;

class NutritionGoalService
{
    private const ACTIVITY_COEFFICIENTS = [
        'sedentary'   => 1.2,
        'light'       => 1.375,
        'moderate'    => 1.55,
        'active'      => 1.725,
        'very_active' => 1.9,
    ];

    private const MEAL_RATIOS = [
        'Petit-déjeuner' => 0.25,
        'Déjeuner'       => 0.35,
        'Collation'      => 0.10,
        'Dîner'          => 0.30,
    ];

    public function dailyGoals(): ?array
    {
        $profile = Profile::first();

        if (!$profile) {
            return null;
        }

        $weight = WeightEntry::latest('measured_at')->value('weight') ?? $profile->current_weight_kg;

        $base = 10 * $weight + 6.25 * $profile->height_cm - 5 * $profile->age;
        $bmr  = round($profile->gender === 'male' ? $base + 5 : $base - 161);

        $coefficient = self::ACTIVITY_COEFFICIENTS[$profile->activity_level] ?? 1.2;
        $tdee        = round($bmr * $coefficient);

        // Même barème que le calcul côté app (src/utils/nutrition.ts) : 1000 kcal/jour de déficit par kg/semaine visé.
        $deficit   = (int) round($profile->weight_loss_rate_kg * 1000);
        $dailyKcal = max(1500, $tdee - $deficit);

        $proteines = round(min($weight * 2, ($dailyKcal * 0.35) / 4));
        $lipides   = round(($dailyKcal * 0.25) / 9);
        $glucides  = max(0, round(($dailyKcal - $proteines * 4 - $lipides * 9) / 4));

        return [
            'kcal'      => $dailyKcal,
            'proteines' => $proteines,
            'glucides'  => $glucides,
            'lipides'   => $lipides,
        ];
    }

    public function mealGoals(string $mealType): ?array
    {
        $daily = $this->dailyGoals();

        if (!$daily) {
            return null;
        }

        $ratio = self::MEAL_RATIOS[$mealType] ?? 0.25;

        return [
            'kcal'      => round($daily['kcal'] * $ratio),
            'proteines' => round($daily['proteines'] * $ratio),
            'glucides'  => round($daily['glucides'] * $ratio),
            'lipides'   => round($daily['lipides'] * $ratio),
        ];
    }
}
