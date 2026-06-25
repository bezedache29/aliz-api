<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'recipe_id', 'food_id', 'food_name', 'food_source',
        'food_brand', 'food_barcode',
        'per100g_kcal', 'per100g_proteines', 'per100g_glucides',
        'per100g_lipides', 'per100g_fibres', 'per100g_sel',
        'quantity_g',
    ];

    protected $casts = [
        'per100g_kcal'       => 'float',
        'per100g_proteines'  => 'float',
        'per100g_glucides'   => 'float',
        'per100g_lipides'    => 'float',
        'per100g_fibres'     => 'float',
        'per100g_sel'        => 'float',
        'quantity_g'         => 'float',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
