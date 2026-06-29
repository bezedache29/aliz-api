<?php

use App\Models\WeightEntry;
use App\Services\RenphoService;
use App\Services\WeightSyncService;
use Illuminate\Support\Facades\Http;

// --- Auth ---

it('requires authentication for weight endpoints', function () {
    $this->getJson('/api/weight')->assertUnauthorized();
    $this->postJson('/api/weight', [])->assertUnauthorized();
    $this->postJson('/api/weight/sync-renpho')->assertUnauthorized();
});

// --- GET /api/weight ---

it('returns empty data when no entries', function () {
    $this->withToken('test-token')
        ->getJson('/api/weight')
        ->assertOk()
        ->assertJson(['data' => []]);
});

it('returns entries sorted by measured_at desc', function () {
    WeightEntry::create(['weight' => 80.0, 'measured_at' => '2026-06-27 08:00:00']);
    WeightEntry::create(['weight' => 79.5, 'measured_at' => '2026-06-28 08:00:00']);
    WeightEntry::create(['weight' => 79.0, 'measured_at' => '2026-06-29 08:00:00']);

    $response = $this->withToken('test-token')
        ->getJson('/api/weight')
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[0]['weight'])->toEqual(79.0);
    expect($data[2]['weight'])->toEqual(80.0);
});

it('respects limit query param', function () {
    foreach (range(1, 5) as $i) {
        WeightEntry::create(['weight' => 80 - $i, 'measured_at' => "2026-06-{$i} 08:00:00"]);
    }

    $this->withToken('test-token')
        ->getJson('/api/weight?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// --- POST /api/weight ---

it('creates a weight entry manually', function () {
    $this->withToken('test-token')
        ->postJson('/api/weight', [
            'weight'      => 78.5,
            'bmi'         => 24.3,
            'measured_at' => '2026-06-29 07:30:00',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'weight', 'bmi', 'measured_at']])
        ->assertJsonPath('data.weight', 78.5);

    expect(WeightEntry::count())->toBe(1);
});

it('validates required fields for manual entry', function () {
    $this->withToken('test-token')
        ->postJson('/api/weight', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weight', 'measured_at']);
});

// --- DELETE /api/weight/{weight} ---

it('deletes a weight entry', function () {
    $entry = WeightEntry::create(['weight' => 80.0, 'measured_at' => '2026-06-29 08:00:00']);

    $this->withToken('test-token')
        ->deleteJson("/api/weight/{$entry->id}")
        ->assertNoContent();

    expect(WeightEntry::count())->toBe(0);
});

it('returns 404 when deleting non-existent entry', function () {
    $this->withToken('test-token')
        ->deleteJson('/api/weight/non-existent-uuid')
        ->assertNotFound();
});

// --- POST /api/weight/sync-renpho ---

it('syncs new Renpho measurements and returns new_entry true', function () {
    $aesKey    = config('services.renpho.aes_key');
    $encrypted = fn(array $data) => base64_encode(openssl_encrypt(json_encode($data), 'AES-128-ECB', $aesKey, OPENSSL_RAW_DATA));

    Http::fake([
        'cloud.renpho.com/renpho-aggregation/user/login' => Http::response([
            'code' => 101,
            'data' => $encrypted(['login' => ['id' => 12345, 'token' => 'fake-token']]),
        ]),
        'cloud.renpho.com/renpho-aggregation/device/count' => Http::response([
            'code' => 101,
            'data' => $encrypted(['scale' => [['tableName' => 'scale_users_0', 'count' => 1, 'userIds' => [12345]]]]),
        ]),
        'cloud.renpho.com/RenphoHealth/scale/queryAllMeasureDataList' => Http::response([
            'code' => 101,
            'data' => $encrypted([[
                'weight'    => 79.5,
                'bmi'       => 24.1,
                'bodyfat'   => 18.5,
                'water'     => 60.0,
                'muscle'    => 55.0,
                'bone'      => 3.2,
                'bmr'       => 1800,
                'protein'   => 18.0,
                'bodyage'   => 35,
                'heartRate' => 68,
                'timeStamp' => 1751184000,
            ]]),
        ]),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/weight/sync-renpho')
        ->assertOk()
        ->assertJsonStructure(['new_entry', 'weight', 'last_synced_at'])
        ->assertJsonPath('new_entry', true)
        ->assertJsonPath('weight', 79.5);

    expect(WeightEntry::count())->toBe(1);
});

it('returns new_entry false when no new measurements', function () {
    $aesKey    = config('services.renpho.aes_key');
    $encrypted = fn(array $data) => base64_encode(openssl_encrypt(json_encode($data), 'AES-128-ECB', $aesKey, OPENSSL_RAW_DATA));

    Http::fake([
        'cloud.renpho.com/renpho-aggregation/user/login' => Http::response([
            'code' => 101,
            'data' => $encrypted(['login' => ['id' => 12345, 'token' => 'fake-token']]),
        ]),
        'cloud.renpho.com/renpho-aggregation/device/count' => Http::response([
            'code' => 101,
            'data' => $encrypted(['scale' => [['tableName' => 'scale_users_0', 'count' => 0, 'userIds' => [12345]]]]),
        ]),
        'cloud.renpho.com/RenphoHealth/scale/queryAllMeasureDataList' => Http::response([
            'code' => 101,
            'data' => $encrypted([]),
        ]),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/weight/sync-renpho')
        ->assertOk()
        ->assertJsonPath('new_entry', false)
        ->assertJsonPath('weight', null);
});

it('returns 502 when Renpho API is unavailable', function () {
    Http::fake([
        'cloud.renpho.com/*' => Http::response(null, 503),
    ]);

    $this->withToken('test-token')
        ->postJson('/api/weight/sync-renpho')
        ->assertStatus(502)
        ->assertJsonStructure(['message']);
});

it('does not create duplicate entries on re-sync', function () {
    $aesKey    = config('services.renpho.aes_key');
    $encrypted = fn(array $data) => base64_encode(openssl_encrypt(json_encode($data), 'AES-128-ECB', $aesKey, OPENSSL_RAW_DATA));

    Http::fake([
        'cloud.renpho.com/renpho-aggregation/user/login' => Http::response([
            'code' => 101,
            'data' => $encrypted(['login' => ['id' => 12345, 'token' => 'fake-token']]),
        ]),
        'cloud.renpho.com/renpho-aggregation/device/count' => Http::response([
            'code' => 101,
            'data' => $encrypted(['scale' => [['tableName' => 'scale_users_0', 'count' => 1, 'userIds' => [12345]]]]),
        ]),
        'cloud.renpho.com/RenphoHealth/scale/queryAllMeasureDataList' => Http::response([
            'code' => 101,
            'data' => $encrypted([['weight' => 79.5, 'timeStamp' => 1751184000]]),
        ]),
    ]);

    $this->withToken('test-token')->postJson('/api/weight/sync-renpho');
    $this->withToken('test-token')->postJson('/api/weight/sync-renpho');

    expect(WeightEntry::count())->toBe(1);
});
