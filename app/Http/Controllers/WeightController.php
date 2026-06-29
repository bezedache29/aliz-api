<?php

namespace App\Http\Controllers;

use App\Models\WeightEntry;
use App\Services\WeightSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit   = (int) $request->query('limit', 30);
        $entries = WeightEntry::latest('measured_at')
            ->limit(max(1, min($limit, 200)))
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'weight'      => ['required', 'numeric', 'min:20', 'max:500'],
            'bmi'         => ['nullable', 'numeric'],
            'bodyfat'     => ['nullable', 'numeric'],
            'water'       => ['nullable', 'numeric'],
            'muscle'      => ['nullable', 'numeric'],
            'bone'        => ['nullable', 'numeric'],
            'bmr'         => ['nullable', 'numeric'],
            'protein'     => ['nullable', 'numeric'],
            'body_age'    => ['nullable', 'numeric'],
            'heart_rate'  => ['nullable', 'numeric'],
            'measured_at' => ['required', 'date'],
        ]);

        $entry = WeightEntry::create($validated);

        return response()->json(['data' => $entry], 201);
    }

    public function destroy(WeightEntry $weight): JsonResponse
    {
        $weight->delete();

        return response()->json(null, 204);
    }

    public function syncRenpho(WeightSyncService $sync): JsonResponse
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $e) {
            Log::error('Renpho sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Impossible de synchroniser avec Renpho : ' . $e->getMessage(),
            ], 502);
        }

        $lastEntry = WeightEntry::latest('measured_at')->first();

        return response()->json([
            'new_entry'      => $result['new_entry'],
            'weight'         => $result['latest_weight'],
            'last_synced_at' => $lastEntry?->measured_at?->toISOString(),
        ]);
    }
}
