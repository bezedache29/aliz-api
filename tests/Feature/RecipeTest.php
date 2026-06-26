<?php

use App\Models\Recipe;

function ingredientPayload(): array
{
    return [
        'food_id'           => 'abc123',
        'food_name'         => 'Farine de blé',
        'food_source'       => 'openfoodfacts',
        'food_brand'        => null,
        'food_barcode'      => null,
        'per100g_kcal'      => 340,
        'per100g_proteines' => 10.0,
        'per100g_glucides'  => 72.0,
        'per100g_lipides'   => 1.0,
        'per100g_fibres'    => 3.0,
        'per100g_sel'       => 0.01,
        'quantity_g'        => 200,
    ];
}

function recipePayload(): array
{
    return [
        'name'           => 'Crêpes bretonnes',
        'category'       => 'Dessert',
        'meal'           => 'Dîner',
        'ingredients'    => [ingredientPayload()],
        'steps'          => ['Mélanger les ingrédients', 'Cuire à la poêle'],
        'is_favorite'    => false,
        'prep_time'      => 10,
        'cook_time'      => 20,
        'seasons'        => ['Printemps', 'Été'],
        'cooking_method' => 'Poêle',
    ];
}


it('requires authentication', function () {
    $this->getJson('/api/recipes')->assertUnauthorized();
});

it('lists recipes', function () {
    Recipe::factory()->count(3)->create();

    $this->withToken('test-token')
        ->getJson('/api/recipes')
        ->assertOk()
        ->assertJsonStructure(['recipes' => []])
        ->assertJsonCount(3, 'recipes');
});

it('creates a recipe', function () {
    $response = $this->withToken('test-token')
        ->postJson('/api/recipes', recipePayload());

    $response->assertCreated()
        ->assertJsonStructure(['id', 'name', 'category', 'meal', 'ingredients', 'steps', 'is_favorite', 'prep_time', 'cook_time', 'seasons', 'cooking_method'])
        ->assertJsonPath('name', 'Crêpes bretonnes')
        ->assertJsonPath('category', 'Dessert')
        ->assertJsonCount(1, 'ingredients');

    expect(Recipe::count())->toBe(1);
});

it('rejects invalid category', function () {
    $payload = array_merge(recipePayload(), ['category' => 'Inexistante']);

    $this->withToken('test-token')
        ->postJson('/api/recipes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);
});

it('rejects empty ingredients array', function () {
    $payload = array_merge(recipePayload(), ['ingredients' => []]);

    $this->withToken('test-token')
        ->postJson('/api/recipes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ingredients']);
});

it('updates a recipe', function () {
    $recipe = Recipe::factory()->create(['name' => 'Ancienne recette']);

    $this->withToken('test-token')
        ->putJson("/api/recipes/{$recipe->id}", array_merge(recipePayload(), ['name' => 'Nouvelle recette']))
        ->assertOk()
        ->assertJsonPath('name', 'Nouvelle recette');
});

it('syncs ingredients on update', function () {
    $recipe = Recipe::factory()->hasIngredients(2)->create();

    $this->withToken('test-token')
        ->putJson("/api/recipes/{$recipe->id}", array_merge(recipePayload(), [
            'ingredients' => [ingredientPayload()],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'ingredients');
});

it('deletes a recipe', function () {
    $recipe = Recipe::factory()->create();

    $this->withToken('test-token')
        ->deleteJson("/api/recipes/{$recipe->id}")
        ->assertNoContent();

    expect(Recipe::count())->toBe(0);
});

it('returns 404 for unknown recipe', function () {
    $this->withToken('test-token')
        ->deleteJson('/api/recipes/'.fake()->uuid())
        ->assertNotFound();
});
