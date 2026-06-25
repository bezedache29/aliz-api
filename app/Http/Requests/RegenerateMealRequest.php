<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_key'  => $this->route('dateKey'),
            'meal_type' => $this->route('mealType'),
        ]);
    }

    public function rules(): array
    {
        return [
            'date_key'  => ['required', 'date_format:Y-m-d'],
            'meal_type' => ['required', 'in:Petit-déjeuner,Déjeuner,Collation,Dîner'],
            'prompt'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
