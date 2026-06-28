<?php

namespace App\Http\Requests;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'category', 'ingredients', 'seasons'] as $field) {
            $rules[$field] = array_map(
                fn ($rule) => $rule === 'required' ? 'sometimes' : $rule,
                $rules[$field]
            );
        }

        return $rules;
    }
}
