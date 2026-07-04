<?php

namespace App\Services;

use App\Models\Activity;

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
                    'name'         => $a['name'] ?? 'Activité',
                    'type'         => $a['sport_type'] ?? ($a['type'] ?? 'Workout'),
                    'distance'     => $a['distance'] ?? null,
                    'moving_time'  => $a['moving_time'] ?? null,
                    'elapsed_time' => $a['elapsed_time'] ?? null,
                    'started_at'   => $a['start_date'],
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $newCount++;
            }
        }

        $latest = Activity::latest('started_at')->first();

        return [
            'new_count'       => $newCount,
            'latest_activity' => $latest,
        ];
    }
}
