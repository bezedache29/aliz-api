<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ActivitySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit      = (int) $request->query('limit', 30);
        $activities = Activity::latest('started_at')
            ->limit(max(1, min($limit, 200)))
            ->get();

        return response()->json(['data' => $activities]);
    }

    public function syncStrava(ActivitySyncService $sync): JsonResponse
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $e) {
            Log::error('Strava activity sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Impossible de synchroniser avec Strava : ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'new_entries'     => $result['new_count'],
            'latest_activity' => $result['latest_activity'],
        ]);
    }
}
