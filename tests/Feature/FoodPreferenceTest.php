<?php

use App\Models\FoodPreference;

it('requires authentication for preferences', function () {
    $this->getJson('/api/preferences')->assertUnauthorized();
});

it('lists preferences grouped by type', function () {
    FoodPreference::create(['food_name' => 'Poulet', 'type' => 'liked']);
    FoodPreference::create(['food_name' => 'Brocoli', 'type' => 'liked']);
    FoodPreference::create(['food_name' => 'Foie', 'type' => 'disliked']);

    $this->withToken('test-token')
        ->getJson('/api/preferences')
        ->assertOk()
        ->assertJsonStructure(['liked', 'disliked'])
        ->assertJsonCount(2, 'liked')
        ->assertJsonCount(1, 'disliked')
        ->assertJsonFragment(['liked' => ['Poulet', 'Brocoli']]);
});

it('returns empty lists when no preferences', function () {
    $this->withToken('test-token')
        ->getJson('/api/preferences')
        ->assertOk()
        ->assertJson(['liked' => [], 'disliked' => []]);
});

it('adds a liked food preference', function () {
    $this->withToken('test-token')
        ->postJson('/api/preferences', ['food_name' => 'Poulet', 'type' => 'liked'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'food_name', 'type']])
        ->assertJsonPath('data.food_name', 'Poulet')
        ->assertJsonPath('data.type', 'liked');

    expect(FoodPreference::count())->toBe(1);
});

it('returns 409 when food already in the same list', function () {
    FoodPreference::create(['food_name' => 'Poulet', 'type' => 'liked']);

    $this->withToken('test-token')
        ->postJson('/api/preferences', ['food_name' => 'Poulet', 'type' => 'liked'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Déjà dans la liste');
});

it('moves food from disliked to liked', function () {
    FoodPreference::create(['food_name' => 'Brocoli', 'type' => 'disliked']);

    $this->withToken('test-token')
        ->postJson('/api/preferences', ['food_name' => 'Brocoli', 'type' => 'liked'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'liked');

    expect(FoodPreference::count())->toBe(1);
    expect(FoodPreference::first()->type)->toBe('liked');
});

it('moves food from liked to disliked', function () {
    FoodPreference::create(['food_name' => 'Foie', 'type' => 'liked']);

    $this->withToken('test-token')
        ->postJson('/api/preferences', ['food_name' => 'Foie', 'type' => 'disliked'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'disliked');

    expect(FoodPreference::count())->toBe(1);
});

it('validates food_name and type', function () {
    $this->withToken('test-token')
        ->postJson('/api/preferences', ['food_name' => 'A', 'type' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['food_name', 'type']);
});

it('deletes a preference', function () {
    $preference = FoodPreference::create(['food_name' => 'Betterave', 'type' => 'disliked']);

    $this->withToken('test-token')
        ->deleteJson("/api/preferences/{$preference->id}")
        ->assertNoContent();

    expect(FoodPreference::count())->toBe(0);
});

it('returns 404 when deleting unknown preference', function () {
    $this->withToken('test-token')
        ->deleteJson('/api/preferences/' . fake()->uuid())
        ->assertNotFound();
});
