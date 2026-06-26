<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'food_name'         => ['required', 'string', 'max:255'],
            'food_id'           => ['nullable', 'string', 'max:255'],
            'food_source'       => ['nullable', 'string', 'max:100'],
            'food_brand'        => ['nullable', 'string', 'max:255'],
            'food_barcode'      => ['nullable', 'string', 'max:100'],
            'per100g_kcal'      => ['nullable', 'numeric', 'min:0'],
            'per100g_proteines' => ['nullable', 'numeric', 'min:0'],
            'per100g_glucides'  => ['nullable', 'numeric', 'min:0'],
            'per100g_lipides'   => ['nullable', 'numeric', 'min:0'],
            'quantity_g'        => ['required', 'numeric', 'min:0'],
            'expiry_date'       => ['nullable', 'date'],
        ];
    }
}
