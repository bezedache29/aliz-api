<?php

namespace App\Services;

use App\Models\WeightEntry;
use Illuminate\Support\Facades\Log;

class WeightSyncService
{
    public function __construct(private RenphoService $renpho) {}

    public function sync(): array
    {
        $email    = config('services.renpho.email');
        $password = config('services.renpho.password');

        $auth = $this->renpho->authenticate($email, $password);

        $lastEntry     = WeightEntry::latest('measured_at')->first();
        $lastUpdatedAt = $lastEntry ? $lastEntry->measured_at->timestamp : 0;

        $measurements = $this->renpho->fetchMeasurements($auth, $lastUpdatedAt);

        if (empty($measurements)) {
            return [
                'new_entry'     => false,
                'latest_weight' => $lastEntry?->weight,
            ];
        }

        $newCount = 0;

        foreach ($measurements as $m) {
            $timestamp = isset($m['timeStamp']) ? (int) $m['timeStamp'] : null;

            if (! $timestamp) {
                continue;
            }

            $entry = WeightEntry::updateOrCreate(
                ['measured_at' => date('Y-m-d H:i:s', $timestamp)],
                [
                    'weight'     => isset($m['weight']) ? (float) $m['weight'] : null,
                    'bmi'        => isset($m['bmi']) ? (float) $m['bmi'] : null,
                    'bodyfat'    => isset($m['bodyfat']) ? (float) $m['bodyfat'] : null,
                    'water'      => isset($m['water']) ? (float) $m['water'] : null,
                    'muscle'     => isset($m['muscle']) ? (float) $m['muscle'] : null,
                    'bone'       => isset($m['bone']) ? (float) $m['bone'] : null,
                    'bmr'        => isset($m['bmr']) ? (float) $m['bmr'] : null,
                    'protein'    => isset($m['protein']) ? (float) $m['protein'] : null,
                    'body_age'   => isset($m['bodyage']) ? (float) $m['bodyage'] : null,
                    'heart_rate' => isset($m['heartRate']) ? (float) $m['heartRate'] : null,
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $newCount++;
            }
        }

        $latest = WeightEntry::latest('measured_at')->first();

        return [
            'new_entry'     => $newCount > 0,
            'latest_weight' => $latest?->weight,
        ];
    }
}
