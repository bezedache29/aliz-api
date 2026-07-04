<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivitySyncService
{
    public function __construct(private StravaService $strava) {}

    public function sync(): array
    {
        $lastEntry = Activity::latest('started_at')->first();
        $after     = $lastEntry ? $lastEntry->started_at->timestamp : null;

        $activities = $this->strava->fetchActivities($after);

        $newCount = 0;

        foreach ($activities as $a) {
            if (empty($a['id']) || empty($a['start_date'])) {
                continue;
            }

            $entry = Activity::updateOrCreate(
                ['strava_id' => $a['id']],
                [
                    'name'                 => $a['name'] ?? 'Activité',
                    'type'                 => $a['sport_type'] ?? ($a['type'] ?? 'Workout'),
                    'distance'             => $a['distance'] ?? null,
                    'moving_time'          => $a['moving_time'] ?? null,
                    'elapsed_time'         => $a['elapsed_time'] ?? null,
                    'total_elevation_gain' => $a['total_elevation_gain'] ?? null,
                    'started_at'           => $a['start_date'],
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $newCount++;

                // Les calories ne sont disponibles que sur l'endpoint détail d'une activité,
                // pas dans la liste — on ne le récupère que pour les nouvelles entrées afin
                // de limiter le nombre d'appels API.
                try {
                    $detail = $this->strava->fetchActivityDetail($a['id']);
                    $entry->update(['calories' => $detail['calories'] ?? null]);
                } catch (Throwable $e) {
                    Log::warning('Strava activity detail fetch failed', [
                        'strava_id' => $a['id'],
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        }

        $latest = Activity::latest('started_at')->first();

        return [
            'new_count'       => $newCount,
            'latest_activity' => $latest,
        ];
    }
}
