<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'food_name', 'food_id', 'food_source', 'food_brand', 'food_barcode',
        'per100g_kcal', 'per100g_proteines', 'per100g_glucides', 'per100g_lipides',
        'quantity_g', 'expiry_date',
    ];

    protected $casts = [
        'per100g_kcal'      => 'float',
        'per100g_proteines' => 'float',
        'per100g_glucides'  => 'float',
        'per100g_lipides'   => 'float',
        'quantity_g'        => 'float',
        'expiry_date'       => 'date:Y-m-d',
    ];
}
