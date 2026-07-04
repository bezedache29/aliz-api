<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\StravaToken;
use App\Services\StravaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StravaAuthController extends Controller
{
    private const APP_CALLBACK = 'aliz://strava-callback';

    public function authorize(StravaService $strava): RedirectResponse
    {
        return redirect()->away($strava->getAuthorizeUrl());
    }

    public function callback(Request $request, StravaService $strava): RedirectResponse
    {
        $code = $request->query('code');

        if (! $code) {
            return redirect()->away(self::APP_CALLBACK . '?error=1');
        }

        try {
            $strava->exchangeCode($code);
        } catch (Throwable $e) {
            Log::error('Strava exchange failed', ['error' => $e->getMessage()]);

            return redirect()->away(self::APP_CALLBACK . '?error=1');
        }

        return redirect()->away(self::APP_CALLBACK . '?connected=1');
    }

    public function status(): JsonResponse
    {
        $token     = StravaToken::first();
        $lastEntry = Activity::latest('started_at')->first();

        return response()->json([
            'connected'      => (bool) $token,
            'athlete_name'   => $token?->athlete_name,
            'last_synced_at' => $lastEntry?->started_at?->toISOString(),
        ]);
    }

    public function disconnect(StravaService $strava): JsonResponse
    {
        $strava->disconnect();

        return response()->json(['connected' => false]);
    }
}
