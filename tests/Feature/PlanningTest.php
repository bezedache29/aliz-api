<?php

use App\Models\FoodPreference;
use App\Models\PlanningMeal;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\StockItem;
use App\Services\LlmService;


it('requires authentication for week', function () {
    $this->getJson('/api/planning/week?from=2026-06-26')->assertUnauthorized();
});

it('returns meals for the week', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();
    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Déjeuner',
        'recipe_id' => $recipe->id,
    ]);

    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=2026-06-23')
        ->assertOk()
        ->assertJsonStructure([
            'meals' => [['date', 'meal_type', 'recipe' => ['id', 'name', 'kcal', 'proteines', 'glucides', 'lipides', 'prep_time', 'cook_time', 'description']]],
        ])
        ->assertJsonCount(1, 'meals')
        ->assertJsonPath('meals.0.date', '2026-06-26')
        ->assertJsonPath('meals.0.meal_type', 'Déjeuner');
});

it('returns empty meals array when no planning for the week', function () {
    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=2026-06-23')
        ->assertOk()
        ->assertJsonPath('meals', []);
});

it('does not return meals with null recipe_id', function () {
    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Dîner',
        'recipe_id' => null,
    ]);

    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=2026-06-26')
        ->assertOk()
        ->assertJsonCount(0, 'meals');
});

it('requires from param', function () {
    $this->withToken('test-token')
        ->getJson('/api/planning/week')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});

it('rejects invalid date format for from param', function () {
    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=26-06-2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});

it('requires authentication for regenerate', function () {
    $this->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertUnauthorized();
});

it('regenerates a meal with existing recipe', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk()
        ->assertJsonStructure(['recipe' => ['id', 'name', 'kcal', 'proteines', 'glucides', 'lipides', 'prep_time', 'cook_time', 'description']])
        ->assertJsonPath('recipe.id', $recipe->id);

    expect(PlanningMeal::count())->toBe(1);
});

it('regenerates a meal with a new LLM-created recipe', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->andReturn([
                'type'        => 'new',
                'name'        => 'Omelette au fromage',
                'description' => 'Simple et rapide à préparer',
                'kcal'        => 350.0,
                'proteines'   => 25.0,
                'glucides'    => 5.0,
                'lipides'     => 28.0,
                'prep_time'   => 5,
                'cook_time'   => 10,
            ]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk()
        ->assertJsonPath('recipe.name', 'Omelette au fromage')
        ->assertJsonPath('recipe.kcal', 350)
        ->assertJsonPath('recipe.description', 'Simple et rapide à préparer');

    expect(Recipe::count())->toBe(1);
    expect(PlanningMeal::count())->toBe(1);
});

it('persists steps and ingredients for a new LLM-created recipe', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->andReturn([
                'type'        => 'new',
                'name'        => 'Omelette au fromage',
                'description' => 'Simple et rapide à préparer',
                'kcal'        => 350.0,
                'proteines'   => 25.0,
                'glucides'    => 5.0,
                'lipides'     => 28.0,
                'prep_time'   => 5,
                'cook_time'   => 10,
                'steps'       => ['Battre les œufs', 'Cuire à la poêle'],
                'ingredients' => [
                    [
                        'food_name'         => 'Œufs',
                        'quantity_g'        => 120.0,
                        'per100g_kcal'      => 150.0,
                        'per100g_proteines' => 13.0,
                        'per100g_glucides'  => 1.0,
                        'per100g_lipides'   => 10.0,
                    ],
                ],
            ]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk();

    $recipe = Recipe::first();
    expect($recipe->steps)->toBe(['Battre les œufs', 'Cuire à la poêle']);
    expect($recipe->is_ai_generated)->toBeTrue();
    expect($recipe->ingredients()->count())->toBe(1);
    expect($recipe->ingredients()->first()->food_name)->toBe('Œufs');
});

it('updates existing planning slot on re-regenerate', function () {
    $recipe1 = Recipe::factory()->hasIngredients(1)->create();
    $recipe2 = Recipe::factory()->hasIngredients(1)->create();

    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Déjeuner',
        'recipe_id' => $recipe1->id,
    ]);

    $this->mock(LlmService::class, function ($mock) use ($recipe2) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe2->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk()
        ->assertJsonPath('recipe.id', $recipe2->id);

    expect(PlanningMeal::count())->toBe(1);
    expect(PlanningMeal::first()->recipe_id)->toBe($recipe2->id);
});

it('accepts optional prompt in regenerate', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(fn($date, $mealType, $recipes, $prompt) => $prompt === 'quelque chose de léger')
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate', [
            'prompt' => 'quelque chose de léger',
        ])
        ->assertOk();
});

it('passes the meal nutritional budget to the LLM when a profile exists', function () {
    Profile::create([
        'first_name'          => 'Christophe',
        'age'                 => 43,
        'gender'              => 'male',
        'height_cm'           => 178,
        'current_weight_kg'   => 82.5,
        'target_weight_kg'    => 75.0,
        'activity_level'      => 'moderate',
        'weight_loss_rate_kg' => 0.5,
    ]);
    $recipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(fn($date, $mealType, $recipes, $prompt, $mealBudget) => $mealBudget === [
                'kcal'      => 762.0,
                'proteines' => 58.0,
                'glucides'  => 85.0,
                'lipides'   => 21.0,
            ])
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk();
});

it('passes a null meal budget to the LLM when no profile exists', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(fn($date, $mealType, $recipes, $prompt, $mealBudget) => $mealBudget === null)
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk();
});

it('passes expiring stock, other stock and food preferences to the LLM', function () {
    StockItem::create([
        'food_name'   => 'Yaourt',
        'quantity_g'  => 150,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);
    FoodPreference::create(['food_name' => 'Saumon', 'type' => 'liked']);
    FoodPreference::create(['food_name' => 'Foie', 'type' => 'disliked']);

    $recipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(function ($date, $mealType, $recipes, $prompt, $mealBudget, $expiringStock, $otherStock, $likedFoods, $dislikedFoods) {
                return count($expiringStock) === 1 && $expiringStock[0]['food_name'] === 'Yaourt'
                    && count($otherStock) === 1 && $otherStock[0]['food_name'] === 'Riz'
                    && $likedFoods === ['Saumon']
                    && $dislikedFoods === ['Foie'];
            })
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk();
});

it('passes meals already planned today (excluding the current slot) to the LLM for variety', function () {
    $lunchRecipe = Recipe::factory()
        ->has(RecipeIngredient::factory()->count(1)->state(['food_name' => 'Poulet']), 'ingredients')
        ->create(['name' => 'Poulet rôti']);
    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Déjeuner',
        'recipe_id' => $lunchRecipe->id,
    ]);

    $dinnerRecipe = Recipe::factory()->hasIngredients(1)->create();

    $this->mock(LlmService::class, function ($mock) use ($dinnerRecipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(function ($date, $mealType, $recipes, $prompt, $mealBudget, $expiringStock, $otherStock, $likedFoods, $dislikedFoods, $plannedTodayMeals) {
                return count($plannedTodayMeals) === 1
                    && $plannedTodayMeals[0]['meal_type'] === 'Déjeuner'
                    && $plannedTodayMeals[0]['name'] === 'Poulet rôti'
                    && $plannedTodayMeals[0]['ingredients'] === ['Poulet'];
            })
            ->andReturn(['type' => 'existing', 'recipe_id' => $dinnerRecipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%AEner/regenerate')
        ->assertOk();
});

it('excludes the slot being regenerated from plannedTodayMeals', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();
    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Déjeuner',
        'recipe_id' => $recipe->id,
    ]);

    $this->mock(LlmService::class, function ($mock) use ($recipe) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->withArgs(function ($date, $mealType, $recipes, $prompt, $mealBudget, $expiringStock, $otherStock, $likedFoods, $dislikedFoods, $plannedTodayMeals) {
                return $plannedTodayMeals === [];
            })
            ->andReturn(['type' => 'existing', 'recipe_id' => $recipe->id]);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertOk();
});

it('returns 500 when LLM response is invalid', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('suggestRecipe')
            ->once()
            ->andReturn(['type' => 'existing']);
    });

    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/D%C3%A9jeuner/regenerate')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Réponse LLM invalide : recipe_id manquant');
});

it('rejects invalid meal type in regenerate', function () {
    $this->withToken('test-token')
        ->postJson('/api/planning/week/2026-06-26/meals/InvalidType/regenerate')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['meal_type']);
});

it('rejects invalid date in regenerate', function () {
    $this->withToken('test-token')
        ->postJson('/api/planning/week/not-a-date/meals/D%C3%A9jeuner/regenerate')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_key']);
});
