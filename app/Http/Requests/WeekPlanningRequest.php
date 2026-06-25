<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeekPlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
