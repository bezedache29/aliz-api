<?php

use App\Models\FoodPreference;
use App\Models\Profile;
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
        ->assertJsonPath('data.is_ai_generated', true)
        ->assertJsonCount(1, 'data.ingredients');

    expect(Recipe::count())->toBe(1);
    expect(Recipe::first()->ingredients()->count())->toBe(1);
    expect(Recipe::first()->is_ai_generated)->toBeTrue();
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

it('does not pass stock to the LLM when use_stock is false', function () {
    StockItem::create([
        'food_name'   => 'Yaourt',
        'quantity_g'  => 150,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(function ($prompt, $expiringStock, $otherStock) {
                return $expiringStock === [] && $otherStock === [];
            })
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Une recette de saison', 'use_stock' => false])
        ->assertCreated();
});

it('passes stock to the LLM by default when use_stock is omitted', function () {
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(function ($prompt, $expiringStock, $otherStock) {
                return count($otherStock) === 1 && $otherStock[0]['food_name'] === 'Riz';
            })
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat avec du riz'])
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

it('passes the real nutritional goals computed from the profile to the LLM', function () {
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

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(fn($prompt, $expiring, $other, $liked, $disliked, $profileContext) => $profileContext === [
                'kcal'      => 2178.0,
                'proteines' => 165.0,
                'glucides'  => 242.0,
                'lipides'   => 61.0,
            ])
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat équilibré'])
        ->assertCreated();
});

it('passes a null profile context to the LLM when no profile exists', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(fn($prompt, $expiring, $other, $liked, $disliked, $profileContext) => $profileContext === null)
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => 'Un plat quelconque'])
        ->assertCreated();
});

it('generates a recipe when the prompt is omitted', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')
            ->once()
            ->withArgs(fn ($prompt) => $prompt === null)
            ->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', [])
        ->assertCreated();
});

it('generates a recipe when the prompt is an empty string', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('generateFullRecipe')->once()->andReturn(llmRecipePayload());
    });

    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => ''])
        ->assertCreated();
});

it('returns 422 when prompt exceeds the max length', function () {
    $this->withToken('test-token')
        ->postJson('/api/recipes/generate', ['prompt' => str_repeat('a', 501)])
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
