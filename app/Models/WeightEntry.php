<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WeightEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'weight',
        'bmi',
        'bodyfat',
        'water',
        'muscle',
        'bone',
        'bmr',
        'protein',
        'body_age',
        'heart_rate',
        'measured_at',
    ];

    protected $casts = [
        'measured_at' => 'datetime',
        'weight'      => 'float',
        'bmi'         => 'float',
        'bodyfat'     => 'float',
        'water'       => 'float',
        'muscle'      => 'float',
        'bone'        => 'float',
        'bmr'         => 'float',
        'protein'     => 'float',
        'body_age'    => 'float',
        'heart_rate'  => 'float',
    ];
}
