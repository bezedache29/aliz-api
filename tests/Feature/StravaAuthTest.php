<?php

use App\Models\StravaToken;
use Illuminate\Support\Facades\Http;

// --- GET /strava/authorize ---

it('redirects to the Strava authorization page', function () {
    $this->get('/strava/authorize')
        ->assertRedirect()
        ->assertRedirectContains('www.strava.com/oauth/authorize');
});

// --- GET /strava/callback ---

it('exchanges the code and redirects to the app on success', function () {
    Http::fake([
        'www.strava.com/oauth/token' => Http::response([
            'access_token'  => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_at'    => now()->addHours(6)->timestamp,
            'athlete'       => ['id' => 42, 'firstname' => 'Christophe', 'lastname' => 'Salou'],
        ]),
    ]);

    $this->get('/strava/callback?code=abc123')
        ->assertRedirect('aliz://strava-callback?connected=1');

    expect(StravaToken::count())->toBe(1);
    expect(StravaToken::first()->athlete_name)->toBe('Christophe Salou');
});

it('redirects to the app with an error when code is missing', function () {
    $this->get('/strava/callback')
        ->assertRedirect('aliz://strava-callback?error=1');
});

it('redirects to the app with an error when exchange fails', function () {
    Http::fake([
        'www.strava.com/oauth/token' => Http::response(null, 400),
    ]);

    $this->get('/strava/callback?code=abc123')
        ->assertRedirect('aliz://strava-callback?error=1');

    expect(StravaToken::count())->toBe(0);
});

// --- GET /api/strava/status ---

it('requires authentication for the status endpoint', function () {
    $this->getJson('/api/strava/status')->assertUnauthorized();
});

it('reports not connected when no token exists', function () {
    $this->withToken('test-token')
        ->getJson('/api/strava/status')
        ->assertOk()
        ->assertJsonPath('connected', false);
});

it('reports connected when a token exists', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
        'athlete_name'  => 'Christophe Salou',
    ]);

    $this->withToken('test-token')
        ->getJson('/api/strava/status')
        ->assertOk()
        ->assertJsonPath('connected', true)
        ->assertJsonPath('athlete_name', 'Christophe Salou');
});

// --- POST /api/strava/disconnect ---

it('requires authentication for the disconnect endpoint', function () {
    $this->postJson('/api/strava/disconnect')->assertUnauthorized();
});

it('deletes the token and revokes access on Strava', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
        'athlete_name'  => 'Christophe Salou',
    ]);

    Http::fake([
        'www.strava.com/oauth/deauthorize' => Http::response(['access_token' => 'fake-access-token']),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/strava/disconnect')
        ->assertOk()
        ->assertJsonPath('connected', false);

    expect(StravaToken::count())->toBe(0);
    Http::assertSent(fn($request) => $request->url() === 'https://www.strava.com/oauth/deauthorize');
});

it('deletes the local token even when Strava revocation fails', function () {
    StravaToken::create([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_at'    => now()->addHour(),
    ]);

    Http::fake([
        'www.strava.com/oauth/deauthorize' => Http::response(null, 500),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/strava/disconnect')
        ->assertOk()
        ->assertJsonPath('connected', false);

    expect(StravaToken::count())->toBe(0);
});

it('is a no-op when no account is connected', function () {
    $this->withToken('test-token')
        ->postJson('/api/strava/disconnect')
        ->assertOk()
        ->assertJsonPath('connected', false);

    expect(StravaToken::count())->toBe(0);
});
