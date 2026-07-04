<?php

use App\Http\Controllers\StravaAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('strava/authorize', [StravaAuthController::class, 'authorize']);
Route::get('strava/callback', [StravaAuthController::class, 'callback']);
