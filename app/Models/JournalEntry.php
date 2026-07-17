<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'date', 'meal_type', 'course', 'name',
        'kcal', 'proteines', 'glucides', 'lipides',
        'quantity_g', 'per100g_kcal', 'per100g_proteines', 'per100g_glucides', 'per100g_lipides',
        'stock_deductions', 'source', 'suggestion_status',
    ];

    protected $casts = [
        'date'               => 'date:Y-m-d',
        'kcal'               => 'float',
        'proteines'          => 'float',
        'glucides'           => 'float',
        'lipides'            => 'float',
        'quantity_g'         => 'float',
        'per100g_kcal'       => 'float',
        'per100g_proteines'  => 'float',
        'per100g_glucides'   => 'float',
        'per100g_lipides'    => 'float',
        'stock_deductions'   => 'array',
    ];
}
