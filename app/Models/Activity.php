<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasUuids;

    protected $fillable = [
        'strava_id',
        'name',
        'type',
        'distance',
        'moving_time',
        'elapsed_time',
        'total_elevation_gain',
        'calories',
        'started_at',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'distance'             => 'float',
        'moving_time'          => 'integer',
        'elapsed_time'         => 'integer',
        'total_elevation_gain' => 'float',
        'calories'             => 'float',
    ];
}
