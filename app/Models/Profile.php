<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name',
        'age',
        'gender',
        'height_cm',
        'current_weight_kg',
        'target_weight_kg',
        'activity_level',
        'weight_loss_rate_kg',
    ];

    protected $casts = [
        'age'                 => 'integer',
        'height_cm'           => 'integer',
        'current_weight_kg'   => 'float',
        'target_weight_kg'    => 'float',
        'weight_loss_rate_kg' => 'float',
    ];
}
