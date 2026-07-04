<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Services\StravaService;
use Illuminate\Console\Command;
use Throwable;

class BackfillStravaActivityDetails extends Command
{
    protected $signature = 'strava:backfill-details';

    protected $description = "Récupère le dénivelé (D+) et les calories manquants sur les activités synchronisées avant l'ajout de ces champs";

    public function handle(StravaService $strava): int
    {
        $activities = Activity::whereNull('total_elevation_gain')
            ->orWhereNull('calories')
            ->get();

        if ($activities->isEmpty()) {
            $this->info('Aucune activité à mettre à jour.');

            return self::SUCCESS;
        }

        foreach ($activities as $activity) {
            try {
                $detail = $strava->fetchActivityDetail($activity->strava_id);

                $activity->update([
                    'total_elevation_gain' => $detail['total_elevation_gain'] ?? $activity->total_elevation_gain,
                    'calories'             => $detail['calories'] ?? $activity->calories,
                ]);

                $this->info("OK : {$activity->name}");
            } catch (Throwable $e) {
                $this->error("Échec pour {$activity->name} : {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
