<?php

namespace App\Http\Requests;

class UpdateStockItemRequest extends StoreStockItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['food_name', 'quantity_g'] as $field) {
            $rules[$field] = array_map(
                fn ($rule) => $rule === 'required' ? 'sometimes' : $rule,
                $rules[$field]
            );
        }

        return $rules;
    }
}
