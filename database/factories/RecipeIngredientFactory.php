<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeIngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'food_id'           => $this->faker->uuid(),
            'food_name'         => $this->faker->word(),
            'food_source'       => $this->faker->randomElement(['openfoodfacts', 'ciqual', 'aprifel', 'manual']),
            'food_brand'        => null,
            'food_barcode'      => null,
            'per100g_kcal'      => $this->faker->randomFloat(2, 0, 900),
            'per100g_proteines' => $this->faker->randomFloat(2, 0, 100),
            'per100g_glucides'  => $this->faker->randomFloat(2, 0, 100),
            'per100g_lipides'   => $this->faker->randomFloat(2, 0, 100),
            'per100g_fibres'    => $this->faker->randomFloat(2, 0, 50),
            'per100g_sel'       => $this->faker->randomFloat(2, 0, 10),
            'quantity_g'        => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
