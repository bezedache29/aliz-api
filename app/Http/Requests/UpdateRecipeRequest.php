<?php

namespace App\Http\Requests;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Tous les champs deviennent optionnels sur un PUT partiel
        $rules['name'][0] = 'sometimes';
        $rules['category'][0] = 'sometimes';
        $rules['ingredients'][0] = 'sometimes';
        $rules['steps'][0] = 'sometimes';
        $rules['seasons'][0] = 'sometimes';

        return $rules;
    }
}
