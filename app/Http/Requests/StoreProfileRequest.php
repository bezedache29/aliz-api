<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'          => ['required', 'string', 'max:100'],
            'age'                 => ['required', 'integer', 'min:1', 'max:120'],
            'gender'              => ['required', 'in:male,female'],
            'height_cm'           => ['required', 'integer', 'min:50', 'max:300'],
            'current_weight_kg'   => ['required', 'numeric', 'min:20', 'max:500'],
            'target_weight_kg'    => ['required', 'numeric', 'min:20', 'max:500'],
            'activity_level'      => ['required', 'in:sedentary,light,moderate,active,very_active'],
            'weight_loss_rate_kg' => ['required', 'numeric', 'in:0.25,0.5,0.75,1.0'],
        ];
    }
}
