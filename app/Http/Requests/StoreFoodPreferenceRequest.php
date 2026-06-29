<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_name' => ['required', 'string', 'min:2', 'max:100'],
            'type'      => ['required', 'in:liked,disliked'],
        ];
    }
}
