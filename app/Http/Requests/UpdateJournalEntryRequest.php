<?php

namespace App\Http\Requests;

class UpdateJournalEntryRequest extends StoreJournalEntryRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['date', 'meal_type', 'name', 'kcal', 'proteines', 'glucides', 'lipides'] as $field) {
            $rules[$field] = array_map(
                fn ($rule) => $rule === 'required' ? 'sometimes' : $rule,
                $rules[$field]
            );
        }

        return $rules;
    }
}
