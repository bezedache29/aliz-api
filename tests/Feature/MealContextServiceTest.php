<?php

use App\Models\FoodPreference;
use App\Models\StockItem;
use App\Services\MealContextService;

it('splits stock between expiring soon and other', function () {
    StockItem::create([
        'food_name'   => 'Yaourt',
        'quantity_g'  => 150,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);

    $stock = app(MealContextService::class)->stock();

    expect($stock['expiring'])->toHaveCount(1);
    expect($stock['expiring'][0]['food_name'])->toBe('Yaourt');
    expect($stock['other'])->toHaveCount(1);
    expect($stock['other'][0]['food_name'])->toBe('Riz');
});

it('reports the stock unit instead of assuming grams', function () {
    StockItem::create(['food_name' => 'Carré gourmand Tomates et mozzarella', 'quantity_g' => 1, 'unit' => 'pièce(s)']);

    $stock = app(MealContextService::class)->stock();

    expect($stock['other'][0])->toBe([
        'food_name' => 'Carré gourmand Tomates et mozzarella',
        'quantity'  => 1.0,
        'unit'      => 'pièce(s)',
    ]);
});

it('defaults the unit to grams when not set', function () {
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);

    $stock = app(MealContextService::class)->stock();

    expect($stock['other'][0]['unit'])->toBe('g');
});

it('includes known per100g macros when available', function () {
    StockItem::create([
        'food_name'         => 'Yaourt',
        'quantity_g'        => 150,
        'unit'              => 'g',
        'per100g_kcal'      => 60,
        'per100g_proteines' => 4,
        'per100g_glucides'  => 5,
        'per100g_lipides'   => 3,
    ]);

    $stock = app(MealContextService::class)->stock();

    expect($stock['other'][0])->toMatchArray([
        'per100g_kcal'      => 60.0,
        'per100g_proteines' => 4.0,
        'per100g_glucides'  => 5.0,
        'per100g_lipides'   => 3.0,
    ]);
});

it('returns empty stock when useStock is false', function () {
    StockItem::create(['food_name' => 'Riz', 'quantity_g' => 500]);

    $stock = app(MealContextService::class)->stock(false);

    expect($stock['expiring'])->toBe([]);
    expect($stock['other'])->toBe([]);
});

it('groups food preferences by liked and disliked', function () {
    FoodPreference::create(['food_name' => 'Saumon', 'type' => 'liked']);
    FoodPreference::create(['food_name' => 'Foie', 'type' => 'disliked']);

    $preferences = app(MealContextService::class)->foodPreferences();

    expect($preferences['liked'])->toBe(['Saumon']);
    expect($preferences['disliked'])->toBe(['Foie']);
});

it('returns empty arrays when no preferences exist', function () {
    $preferences = app(MealContextService::class)->foodPreferences();

    expect($preferences['liked'])->toBe([]);
    expect($preferences['disliked'])->toBe([]);
});
