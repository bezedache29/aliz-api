<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanningMealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date'      => $this->faker->dateTimeBetween('today', '+7 days')->format('Y-m-d'),
            'meal_type' => $this->faker->randomElement(['Petit-déjeuner', 'Déjeuner', 'Collation', 'Dîner']),
            'recipe_id' => Recipe::factory(),
        ];
    }
}
