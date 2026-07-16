<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomFoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'brand'             => ['nullable', 'string', 'max:255'],
            'barcode'           => ['nullable', 'string', 'max:100', 'unique:custom_foods,barcode'],
            'per100g_kcal'      => ['required', 'numeric', 'min:0'],
            'per100g_proteines' => ['required', 'numeric', 'min:0'],
            'per100g_glucides'  => ['required', 'numeric', 'min:0'],
            'per100g_lipides'   => ['required', 'numeric', 'min:0'],
            'per100g_fibres'    => ['nullable', 'numeric', 'min:0'],
            'per100g_sel'       => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
