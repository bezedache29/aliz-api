<?php

use App\Models\Activity;
use App\Models\StravaToken;
use Illuminate\Support\Facades\Http;

it('backfills total_elevation_gain and calories for activities missing them', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    $activity = Activity::create([
        'strava_id'  => 111,
        'name'       => 'Sortie VTT',
        'type'       => 'MountainBikeRide',
        'started_at' => '2026-07-01 08:00:00',
    ]);

    Http::fake([
        'www.strava.com/api/v3/activities/111' => Http::response([
            'total_elevation_gain' => 420.0,
            'calories'             => 870.2,
        ]),
    ]);

    $this->artisan('strava:backfill-details')->assertExitCode(0);

    $activity->refresh();
    expect($activity->total_elevation_gain)->toBe(420.0);
    expect($activity->calories)->toBe(870.2);
});

it('does nothing when no activity is missing data', function () {
    Activity::create([
        'strava_id'            => 111,
        'name'                 => 'Sortie VTT',
        'type'                 => 'MountainBikeRide',
        'started_at'           => '2026-07-01 08:00:00',
        'total_elevation_gain' => 420.0,
        'calories'             => 870.2,
    ]);

    Http::fake();

    $this->artisan('strava:backfill-details')->assertExitCode(0);

    Http::assertNothingSent();
});

it('continues with other activities when one detail fetch fails', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    $failing = Activity::create([
        'strava_id'  => 111,
        'name'       => 'Sortie qui échoue',
        'type'       => 'Ride',
        'started_at' => '2026-07-01 08:00:00',
    ]);
    $succeeding = Activity::create([
        'strava_id'  => 222,
        'name'       => 'Sortie qui réussit',
        'type'       => 'Ride',
        'started_at' => '2026-07-02 08:00:00',
    ]);

    Http::fake([
        'www.strava.com/api/v3/activities/111' => Http::response(null, 500),
        'www.strava.com/api/v3/activities/222' => Http::response([
            'total_elevation_gain' => 100.0,
            'calories'             => 200.0,
        ]),
    ]);

    $this->artisan('strava:backfill-details')->assertExitCode(0);

    $failing->refresh();
    $succeeding->refresh();
    expect($failing->calories)->toBeNull();
    expect($succeeding->calories)->toBe(200.0);
});
