<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'          => ['sometimes', 'string', 'max:100'],
            'age'                 => ['sometimes', 'integer', 'min:1', 'max:120'],
            'gender'              => ['sometimes', 'in:male,female'],
            'height_cm'           => ['sometimes', 'integer', 'min:50', 'max:300'],
            'current_weight_kg'   => ['sometimes', 'numeric', 'min:20', 'max:500'],
            'target_weight_kg'    => ['sometimes', 'numeric', 'min:20', 'max:500'],
            'activity_level'      => ['sometimes', 'in:sedentary,light,moderate,active,very_active'],
            'weight_loss_rate_kg' => ['sometimes', 'numeric', 'in:0.25,0.5,0.75,1.0'],
        ];
    }
}
