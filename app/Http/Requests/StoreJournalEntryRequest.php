<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'               => ['required', 'date_format:Y-m-d'],
            'meal_type'          => ['required', 'in:Petit-déjeuner,Déjeuner,Collation,Dîner'],
            'course'             => ['nullable', 'string', 'max:100'],
            'name'               => ['required', 'string', 'max:255'],
            'kcal'               => ['required', 'numeric', 'min:0'],
            'proteines'          => ['required', 'numeric', 'min:0'],
            'glucides'           => ['required', 'numeric', 'min:0'],
            'lipides'            => ['required', 'numeric', 'min:0'],
            'quantity_g'         => ['nullable', 'numeric', 'min:0'],
            'per100g_kcal'       => ['nullable', 'numeric', 'min:0'],
            'per100g_proteines'  => ['nullable', 'numeric', 'min:0'],
            'per100g_glucides'   => ['nullable', 'numeric', 'min:0'],
            'per100g_lipides'    => ['nullable', 'numeric', 'min:0'],
            'stock_deductions'   => ['nullable', 'array'],
            'source'             => ['nullable', 'in:manual,ai_suggestion'],
            'suggestion_status'  => ['nullable', 'in:accepted,modified'],
        ];
    }
}
