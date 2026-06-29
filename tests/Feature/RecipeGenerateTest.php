<?php

use App\Models\FoodPreference;
use App\Models\Recipe;
use App\Models\StockItem;
use App\Services\LlmService;

function llmRecipePayload(): array
{
    return [
        'name'                => 'Poulet rôti aux légumes',
        'description'         => 'Un plat savoureux et équilibré.',
        'category'            => 'Plat principal',
        'meal'                => 'Dîner',
        'cooking_method'      => 'Four',
        'seasons'             => ['Automne', 'Hiver'],
        'prep_time'           => 15,
        'cook_time'           => 45,
        'kcal_estimated'      => 520.0,
        'proteines_estimated' => 42.0,
        'glucides_estimated'  => 18.0,
        'lipides_estimated'   => 28.0,
        'steps'               => ['Préchauffer le four à 200°C', 'Enfourner 45 min'],
        'ingredients'         => [
            [
                'food_name'         => 'Poulet',
                'quantity_g'        => 300.0,
                'per100g_kcal'      => 165.0,
                'per100g_proteines' => 31.0,
                'per100g_glucides'  => 0.0,
                'per100g_lipides'   => 3.6,
                'from_stock'        => true,
            ],
        ],
    ];
}

it('requires authentication for generate', function () {
    $this->postJson('/api/recipes/generate', ['prompt' => 'Une recette rapide'])->assertUnauthorized();
});

it('generates and saves a recipe when save=true', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')->once()->andReturn(llmRecipePayload());
    });

    $response = $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat avec du poulet', 'save' => true]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'category', 'meal', 'ingredients', 'steps', 'cooking_method']])
        ->assertJsonPath('data.name', 'Poulet rôti aux légumes')
        ->assertJsonPath('data.category', 'Plat principal')
        ->assertJsonCount(1, 'data.ingredients');

    expect(Recipe::count())->toBe(1);
    expect(Recipe::first()->ingredients()->count())->toBe(1);
});

it('does not save when save=false and returns raw LLM data', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')->once()->andReturn(llmRecipePayload());
    });

    $response = $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat léger', 'save' => false]);

    $response->assertOk()
        ->assertJsonPath('name', 'Poulet rôti aux légumes');

    expect(Recipe::count())->toBe(0);
});

it('saves by default when save is omitted', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')->once()->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Une soupe chaude'])
        ->assertCreated();

    expect(Recipe::count())->toBe(1);
});

it('passes expiring stock to the LLM', function () {
    StockItem::create([
        'food_name'   => 'Yaourt',
        'quantity_g'  => 150,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(function ($prompt, $expiringStock, $otherStock) {
                return count($expiringStock) === 1 && $expiringStock[0]['food_name'] === 'Yaourt';
            })
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Utilise le yaourt'])
        ->assertCreated();
});

it('passes disliked foods to the LLM', function () {
    FoodPreference::create(['food_name' => 'Foie', 'type' => 'disliked']);

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(function ($prompt, $expiring, $other, $liked, $disliked) {
                return in_array('Foie', $disliked);
            })
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un repas sans abats'])
        ->assertCreated();
});

it('returns 422 when prompt is missing', function () {
    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt']);
});

it('returns 422 when prompt is too short', function () {
    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'ab'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt']);
});

it('returns 422 when LLM returns invalid JSON', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->andThrow(new \RuntimeException('Réponse LLM invalide : not json'));
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat simple'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Réponse LLM invalide : not json');
});
