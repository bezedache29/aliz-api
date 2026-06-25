<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningMeal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['date', 'meal_type', 'recipe_id'];

    protected $casts = ['date' => 'date'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->with('ingredients');
    }
}
