<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories = ['Petit-déjeuner', 'Brunch', 'Entrée', 'Plat principal', 'Soupe', 'Dessert', 'Encas', 'Apéritif', 'Boulangerie', 'Sauce & condiments'];
        $meals = ['Petit-déjeuner', 'Déjeuner', 'Collation', 'Dîner'];
        $seasons = ['Printemps', 'Été', 'Automne', 'Hiver'];
        $cookingMethods = ['Four', 'Poêle', 'Cookeo', 'Barbecue', 'Froid'];
        $sources = ['openfoodfacts', 'ciqual', 'aprifel', 'manual'];

        return [
            'name'                        => ['required', 'string', 'max:255'],
            'category'                    => ['required', 'string', 'in:'.implode(',', $categories)],
            'meal'                        => ['nullable', 'string', 'in:'.implode(',', $meals)],
            'ingredients'                 => ['required', 'array', 'min:1'],
            'ingredients.*.food_id'       => ['required', 'string'],
            'ingredients.*.food_name'     => ['required', 'string'],
            'ingredients.*.food_source'   => ['required', 'string', 'in:'.implode(',', $sources)],
            'ingredients.*.food_brand'    => ['nullable', 'string'],
            'ingredients.*.food_barcode'  => ['nullable', 'string'],
            'ingredients.*.per100g_kcal'      => ['required', 'numeric', 'min:0'],
            'ingredients.*.per100g_proteines' => ['required', 'numeric', 'min:0'],
            'ingredients.*.per100g_glucides'  => ['required', 'numeric', 'min:0'],
            'ingredients.*.per100g_lipides'   => ['required', 'numeric', 'min:0'],
            'ingredients.*.per100g_fibres'    => ['required', 'numeric', 'min:0'],
            'ingredients.*.per100g_sel'       => ['required', 'numeric', 'min:0'],
            'ingredients.*.quantity_g'        => ['required', 'numeric', 'min:0'],
            'steps'                       => ['nullable', 'array'],
            'steps.*'                     => ['string'],
            'is_favorite'                 => ['boolean'],
            'prep_time'                   => ['nullable', 'integer', 'min:0', 'max:65535'],
            'cook_time'                   => ['nullable', 'integer', 'min:0', 'max:65535'],
            'seasons'                     => ['required', 'array'],
            'seasons.*'                   => ['string', 'in:'.implode(',', $seasons)],
            'cooking_method'              => ['nullable', 'string', 'in:'.implode(',', $cookingMethods)],
        ];
    }
}
