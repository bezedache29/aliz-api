<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StravaToken extends Model
{
    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
        'athlete_id',
        'athlete_name',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
