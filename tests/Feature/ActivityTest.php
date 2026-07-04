<?php

use App\Models\Activity;
use App\Models\StravaToken;
use Illuminate\Support\Facades\Http;

// --- Auth ---

it('requires authentication for activity endpoints', function () {
    $this->getJson('/api/activities')->assertUnauthorized();
    $this->postJson('/api/activities/sync-strava')->assertUnauthorized();
});

// --- GET /api/activities ---

it('returns empty data when no activities', function () {
    $this->withToken('test-token')
        ->getJson('/api/activities')
        ->assertOk()
        ->assertJson(['data' => []]);
});

it('returns activities sorted by started_at desc', function () {
    Activity::create(['strava_id' => 1, 'name' => 'Run 1', 'type' => 'Run', 'started_at' => '2026-06-27 08:00:00']);
    Activity::create(['strava_id' => 2, 'name' => 'Run 2', 'type' => 'Run', 'started_at' => '2026-06-29 08:00:00']);
    Activity::create(['strava_id' => 3, 'name' => 'Run 3', 'type' => 'Run', 'started_at' => '2026-06-28 08:00:00']);

    $response = $this->withToken('test-token')
        ->getJson('/api/activities')
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[0]['name'])->toBe('Run 2');
    expect($data[2]['name'])->toBe('Run 1');
});

it('respects limit query param', function () {
    foreach (range(1, 5) as $i) {
        Activity::create([
            'strava_id'  => $i,
            'name'       => "Run {$i}",
            'type'       => 'Run',
            'started_at' => "2026-06-{$i} 08:00:00",
        ]);
    }

    $this->withToken('test-token')
        ->getJson('/api/activities?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// --- POST /api/activities/sync-strava ---

it('returns 502 when no Strava account is connected', function () {
    $this->withToken('test-token')
        ->postJson('/api/activities/sync-strava')
        ->assertStatus(502)
        ->assertJsonStructure(['message']);
});

it('syncs new Strava activities and returns new entries count', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
        'athlete_id'    => 123,
        'athlete_name'  => 'Christophe Salou',
    ]);

    Http::fake([
        'www.strava.com/api/v3/athlete/activities*' => Http::sequence()
            ->push([
                [
                    'id'                   => 987654321,
                    'name'                 => 'Sortie VTT',
                    'sport_type'           => 'MountainBikeRide',
                    'distance'             => 15000.5,
                    'moving_time'          => 3600,
                    'elapsed_time'         => 3700,
                    'total_elevation_gain' => 420.0,
                    'start_date'           => '2026-07-01T08:00:00Z',
                ],
            ])
            ->push([]),
        'www.strava.com/api/v3/activities/987654321' => Http::response(['calories' => 870.2]),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/activities/sync-strava')
        ->assertOk()
        ->assertJsonPath('new_entries', 1)
        ->assertJsonPath('latest_activity.name', 'Sortie VTT');

    $activity = Activity::first();
    expect($activity->total_elevation_gain)->toBe(420.0);
    expect($activity->calories)->toBe(870.2);
});

it('does not fail the sync when the activity detail fetch fails', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    Http::fake([
        'www.strava.com/api/v3/athlete/activities*' => Http::sequence()
            ->push([[
                'id'         => 555,
                'name'       => 'Sortie',
                'sport_type' => 'Ride',
                'start_date' => '2026-07-01T08:00:00Z',
            ]])
            ->push([]),
        'www.strava.com/api/v3/activities/555' => Http::response(null, 500),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/activities/sync-strava')
        ->assertOk()
        ->assertJsonPath('new_entries', 1);

    $activity = Activity::first();
    expect($activity)->not->toBeNull();
    expect($activity->calories)->toBeNull();
});

it('does not create duplicate activities on re-sync', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    Http::fake([
        'www.strava.com/api/v3/athlete/activities*' => Http::sequence()
            ->push([[
                'id'         => 111,
                'name'       => 'Balade',
                'sport_type' => 'Ride',
                'start_date' => '2026-07-01T08:00:00Z',
            ]])
            ->push([])
            ->push([])
            ->push([]),
        'www.strava.com/api/v3/activities/111' => Http::response(['calories' => 300.0]),
    ]);

    $this->withToken('test-token')->postJson('/api/activities/sync-strava');
    $this->withToken('test-token')->postJson('/api/activities/sync-strava');

    expect(Activity::count())->toBe(1);
});

it('returns 502 when Strava API is unavailable', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    Http::fake([
        'www.strava.com/api/v3/*' => Http::response(null, 503),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/activities/sync-strava')
        ->assertStatus(502)
        ->assertJsonStructure(['message']);
});
