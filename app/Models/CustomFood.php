<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomFood extends Model
{
    use HasUuids;

    protected $table = 'custom_foods';

    protected $fillable = [
        'name', 'brand', 'barcode',
        'per100g_kcal', 'per100g_proteines', 'per100g_glucides', 'per100g_lipides',
        'per100g_fibres', 'per100g_sel',
    ];

    protected $casts = [
        'per100g_kcal'      => 'float',
        'per100g_proteines' => 'float',
        'per100g_glucides'  => 'float',
        'per100g_lipides'   => 'float',
        'per100g_fibres'    => 'float',
        'per100g_sel'       => 'float',
    ];
}
