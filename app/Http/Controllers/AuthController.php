<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    // TODO: Strava OAuth flow
    // Flow: app → POST /auth/strava/callback {code} → échange le code Strava
    //       → crée/retrouve le UserProfile → retourne un token Sanctum
    //
    // Packages requis (déjà installés) : laravel/socialite + socialiteproviders/strava
    // Config à ajouter dans config/services.php :
    //   'strava' => ['client_id' => env('STRAVA_CLIENT_ID'), ...]
}
