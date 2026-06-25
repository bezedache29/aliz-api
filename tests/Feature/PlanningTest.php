<?php

use App\Models\PlanningMeal;
use App\Models\Recipe;
use App\Services\LlmService;

beforeEach(function () {
    config(['app.static_api_token' => 'test-token']);
});

it('requires authentication for week', function () {
    $this->getJson('/api/planning/week?from=2026-06-26')->assertUnauthorized();
});

it('returns meals for a given day', function () {
    $recipe = Recipe::factory()->hasIngredients(1)->create();
    PlanningMeal::factory()->create([
        'date'      => '2026-06-26',
        'meal_type' => 'Déjeuner',
        'recipe_id' => $recipe->id,
    ]);

    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=2026-06-26')
        ->assertOk()
        ->assertJsonStructure([
            'meals' => [['meal_type', 'recipe' => ['id', 'name', 'kcal', 'proteines', 'glucides', 'lipides', 'prep_time', 'cook_time', 'description']]],
        ])
        ->assertJsonCount(1, 'meals')
        ->assertJsonPath('meals.0.meal_type', 'Déjeuner');
});

it('returns empty meals array when no planning for that day', function () {
    $this->withToken('test-token')
        ->getJson('/api/planning/week?from=2026-06-26')
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
