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
}
