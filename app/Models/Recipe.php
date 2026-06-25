<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'category', 'meal', 'steps',
        'is_favorite', 'prep_time', 'cook_time', 'seasons', 'cooking_method',
        'description', 'kcal_estimated', 'proteines_estimated', 'glucides_estimated', 'lipides_estimated',
    ];

    protected $casts = [
        'steps'       => 'array',
        'seasons'     => 'array',
        'is_favorite' => 'boolean',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function macros(): array
    {
        if ($this->kcal_estimated !== null
            && $this->proteines_estimated !== null
            && $this->glucides_estimated !== null
            && $this->lipides_estimated !== null
        ) {
            return [
                'kcal'      => (float) $this->kcal_estimated,
                'proteines' => (float) $this->proteines_estimated,
                'glucides'  => (float) $this->glucides_estimated,
                'lipides'   => (float) $this->lipides_estimated,
            ];
        }

        return $this->ingredients->reduce(function (array $carry, RecipeIngredient $ingredient) {
            $ratio = $ingredient->quantity_g / 100;
            return [
                'kcal'      => $carry['kcal'] + round($ingredient->per100g_kcal * $ratio, 2),
                'proteines' => $carry['proteines'] + round($ingredient->per100g_proteines * $ratio, 2),
                'glucides'  => $carry['glucides'] + round($ingredient->per100g_glucides * $ratio, 2),
                'lipides'   => $carry['lipides'] + round($ingredient->per100g_lipides * $ratio, 2),
            ];
        }, ['kcal' => 0.0, 'proteines' => 0.0, 'glucides' => 0.0, 'lipides' => 0.0]);
    }
}
