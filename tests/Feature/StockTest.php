<?php

use App\Models\StockItem;

function stockPayload(): array
{
    return [
        'food_name'         => 'Flocons d\'avoine',
        'food_id'           => 'oat123',
        'food_source'       => 'openfoodfacts',
        'food_brand'        => 'Bjorg',
        'food_barcode'      => '3123456789012',
        'per100g_kcal'      => 370.0,
        'per100g_proteines' => 13.0,
        'per100g_glucides'  => 58.0,
        'per100g_lipides'   => 7.0,
        'quantity_g'        => 500,
        'expiry_date'       => '2026-12-31',
    ];
}

beforeEach(function () {
    config(['app.static_api_token' => 'test-token']);
});

it('retourne la liste des articles en stock', function () {
    StockItem::create([
        'food_name'  => 'Riz basmati',
        'quantity_g' => 1000,
    ]);

    $this->withToken(config('app.static_api_token'))
        ->getJson('/api/stock')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'food_name', 'quantity_g']]]);
});

it('retourne une liste vide quand le stock est vide', function () {
    $this->withToken(config('app.static_api_token'))
        ->getJson('/api/stock')
        ->assertOk()
        ->assertJson(['data' => []]);
});

it('trie les articles par date de péremption (nulls en dernier) puis par nom', function () {
    StockItem::create(['food_name' => 'Yaourt', 'quantity_g' => 200, 'expiry_date' => '2026-07-10']);
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);
    StockItem::create(['food_name' => 'Lait', 'quantity_g' => 1000, 'expiry_date' => '2026-07-01']);

    $response = $this->withToken(config('app.static_api_token'))->getJson('/api/stock');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('food_name')->toArray();
    expect($names)->toBe(['Lait', 'Yaourt', 'Riz']);
});

it('crée un article en stock', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', stockPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'food_name', 'quantity_g', 'expiry_date']])
        ->assertJsonPath('data.food_name', 'Flocons d\'avoine')
        ->assertJsonPath('data.quantity_g', 500);

    $this->assertDatabaseHas('stock_items', ['food_name' => 'Flocons d\'avoine']);
});

it('crée un article minimal sans macros ni date de péremption', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', ['food_name' => 'Sel', 'quantity_g' => 250])
        ->assertCreated()
        ->assertJsonPath('data.food_name', 'Sel')
        ->assertJsonPath('data.expiry_date', null);
});

it('refuse la création sans food_name', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', ['quantity_g' => 100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['food_name']);
});

it('refuse la création sans quantity_g', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', ['food_name' => 'Sucre'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity_g']);
});

it('refuse une quantity_g négative', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', ['food_name' => 'Farine', 'quantity_g' => -100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity_g']);
});

it('refuse une quantity_g à zéro', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/stock', ['food_name' => 'Farine', 'quantity_g' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity_g']);
});

it('met à jour un article en stock', function () {
    $item = StockItem::create(['food_name' => 'Lentilles', 'quantity_g' => 400]);

    $this->withToken(config('app.static_api_token'))
        ->putJson("/api/stock/{$item->id}", ['quantity_g' => 200])
        ->assertOk()
        ->assertJsonPath('data.quantity_g', 200);

    $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'quantity_g' => 200]);
});

it('met à jour la date de péremption', function () {
    $item = StockItem::create(['food_name' => 'Yaourt', 'quantity_g' => 150]);

    $this->withToken(config('app.static_api_token'))
        ->putJson("/api/stock/{$item->id}", ['expiry_date' => '2026-08-01'])
        ->assertOk()
        ->assertJsonPath('data.expiry_date', '2026-08-01');
});

it('retourne 404 si l\'article n\'existe pas (update)', function () {
    $this->withToken(config('app.static_api_token'))
        ->putJson('/api/stock/uuid-inconnu', ['quantity_g' => 100])
        ->assertNotFound();
});

it('supprime un article du stock', function () {
    $item = StockItem::create(['food_name' => 'Quinoa', 'quantity_g' => 300]);

    $this->withToken(config('app.static_api_token'))
        ->deleteJson("/api/stock/{$item->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('stock_items', ['id' => $item->id]);
});

it('retourne 404 si l\'article n\'existe pas (delete)', function () {
    $this->withToken(config('app.static_api_token'))
        ->deleteJson('/api/stock/uuid-inconnu')
        ->assertNotFound();
});

it('rejette les requêtes non authentifiées', function () {
    $item = StockItem::create(['food_name' => 'Test', 'quantity_g' => 100]);

    $this->getJson('/api/stock')->assertUnauthorized();
    $this->postJson('/api/stock', stockPayload())->assertUnauthorized();
    $this->putJson("/api/stock/{$item->id}", ['quantity_g' => 50])->assertUnauthorized();
    $this->deleteJson("/api/stock/{$item->id}")->assertUnauthorized();
});
