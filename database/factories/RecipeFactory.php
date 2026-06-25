<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => $this->faker->words(3, true),
            'category'       => $this->faker->randomElement(['Petit-déjeuner', 'Plat principal', 'Dessert', 'Encas']),
            'meal'           => $this->faker->randomElement(['Petit-déjeuner', 'Déjeuner', 'Dîner', null]),
            'steps'          => [$this->faker->sentence(), $this->faker->sentence()],
            'is_favorite'    => false,
            'prep_time'      => $this->faker->numberBetween(5, 30),
            'cook_time'      => $this->faker->numberBetween(10, 60),
            'seasons'        => ['Printemps'],
            'cooking_method' => $this->faker->randomElement(['Four', 'Poêle', null]),
        ];
    }

    public function hasIngredients(int $count = 1): static
    {
        return $this->has(RecipeIngredientFactory::new()->count($count), 'ingredients');
    }
}
