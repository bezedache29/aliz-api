<?php

use App\Models\CustomFood;

it('requires authentication for custom foods', function () {
    $this->getJson('/api/custom-foods')->assertUnauthorized();
});

it('lists custom foods ordered by name', function () {
    CustomFood::create([
        'name' => 'Yaourt maison', 'per100g_kcal' => 60, 'per100g_proteines' => 4,
        'per100g_glucides' => 5, 'per100g_lipides' => 2,
    ]);
    CustomFood::create([
        'name' => 'Compote maison', 'per100g_kcal' => 50, 'per100g_proteines' => 0,
        'per100g_glucides' => 12, 'per100g_lipides' => 0,
    ]);

    $this->withToken('test-token')
        ->getJson('/api/custom-foods')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'brand', 'barcode', 'per100g_kcal']]])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Compote maison');
});

it('creates a custom food with a barcode', function () {
    $this->withToken('test-token')
        ->postJson('/api/custom-foods', [
            'name'              => 'Gâteau marbré maison',
            'brand'             => null,
            'barcode'           => '3123456789012',
            'per100g_kcal'      => 350,
            'per100g_proteines' => 5,
            'per100g_glucides'  => 45,
            'per100g_lipides'   => 15,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gâteau marbré maison')
        ->assertJsonPath('data.barcode', '3123456789012');

    expect(CustomFood::count())->toBe(1);
});

it('rejects a duplicate barcode', function () {
    CustomFood::create([
        'name' => 'Existant', 'barcode' => '3123456789012', 'per100g_kcal' => 100,
        'per100g_proteines' => 1, 'per100g_glucides' => 1, 'per100g_lipides' => 1,
    ]);

    $this->withToken('test-token')
        ->postJson('/api/custom-foods', [
            'name'              => 'Doublon',
            'barcode'           => '3123456789012',
            'per100g_kcal'      => 100,
            'per100g_proteines' => 1,
            'per100g_glucides'  => 1,
            'per100g_lipides'   => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode']);
});

it('validates required macro fields', function () {
    $this->withToken('test-token')
        ->postJson('/api/custom-foods', ['name' => 'Sans macros'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'per100g_kcal', 'per100g_proteines', 'per100g_glucides', 'per100g_lipides',
        ]);
});

it('deletes a custom food', function () {
    $food = CustomFood::create([
        'name' => 'A supprimer', 'per100g_kcal' => 10, 'per100g_proteines' => 1,
        'per100g_glucides' => 1, 'per100g_lipides' => 1,
    ]);

    $this->withToken('test-token')
        ->deleteJson("/api/custom-foods/{$food->id}")
        ->assertNoContent();

    expect(CustomFood::count())->toBe(0);
});

it('returns 404 when deleting an unknown custom food', function () {
    $this->withToken('test-token')
        ->deleteJson('/api/custom-foods/' . fake()->uuid())
        ->assertNotFound();
});
