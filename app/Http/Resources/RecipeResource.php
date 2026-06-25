<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'category'       => $this->category,
            'meal'           => $this->meal,
            'ingredients'    => $this->whenLoaded('ingredients', fn () => $this->ingredients->map(fn ($i) => [
                'food_id'           => $i->food_id,
                'food_name'         => $i->food_name,
                'food_source'       => $i->food_source,
                'food_brand'        => $i->food_brand,
                'food_barcode'      => $i->food_barcode,
                'per100g_kcal'      => $i->per100g_kcal,
                'per100g_proteines' => $i->per100g_proteines,
                'per100g_glucides'  => $i->per100g_glucides,
                'per100g_lipides'   => $i->per100g_lipides,
                'per100g_fibres'    => $i->per100g_fibres,
                'per100g_sel'       => $i->per100g_sel,
                'quantity_g'        => $i->quantity_g,
            ])->all(), []),
            'steps'          => $this->steps,
            'is_favorite'    => $this->is_favorite,
            'prep_time'      => $this->prep_time,
            'cook_time'      => $this->cook_time,
            'seasons'        => $this->seasons,
            'cooking_method' => $this->cooking_method,
        ];
    }
}
