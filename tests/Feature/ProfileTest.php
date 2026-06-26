<?php

use App\Models\Profile;

function profilePayload(): array
{
    return [
        'first_name'          => 'Christophe',
        'age'                 => 43,
        'gender'              => 'male',
        'height_cm'           => 178,
        'current_weight_kg'   => 82.5,
        'target_weight_kg'    => 75.0,
        'activity_level'      => 'moderate',
        'weight_loss_rate_kg' => 0.5,
    ];
}

it('retourne le profil existant', function () {
    Profile::create(profilePayload());

    $this->withToken(config('app.static_api_token'))
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'first_name', 'age', 'gender', 'height_cm',
            'current_weight_kg', 'target_weight_kg', 'activity_level', 'weight_loss_rate_kg',
        ]])
        ->assertJsonPath('data.first_name', 'Christophe');
});

it('retourne 404 si aucun profil n\'existe', function () {
    $this->withToken(config('app.static_api_token'))
        ->getJson('/api/profile')
        ->assertNotFound();
});

it('crée le profil', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', profilePayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'first_name', 'age', 'gender']])
        ->assertJsonPath('data.first_name', 'Christophe')
        ->assertJsonPath('data.activity_level', 'moderate');

    $this->assertDatabaseHas('profiles', ['first_name' => 'Christophe']);
});

it('refuse de créer un second profil', function () {
    Profile::create(profilePayload());

    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', profilePayload())
        ->assertStatus(409);
});

it('refuse la création sans champs obligatoires', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'first_name', 'age', 'gender', 'height_cm',
            'current_weight_kg', 'target_weight_kg', 'activity_level', 'weight_loss_rate_kg',
        ]);
});

it('refuse un gender invalide', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', array_merge(profilePayload(), ['gender' => 'other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender']);
});

it('refuse un activity_level invalide', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', array_merge(profilePayload(), ['activity_level' => 'extreme']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['activity_level']);
});

it('refuse un weight_loss_rate_kg non autorisé', function () {
    $this->withToken(config('app.static_api_token'))
        ->postJson('/api/profile', array_merge(profilePayload(), ['weight_loss_rate_kg' => 2.0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weight_loss_rate_kg']);
});

it('met à jour le profil partiellement', function () {
    $profile = Profile::create(profilePayload());

    $this->withToken(config('app.static_api_token'))
        ->putJson('/api/profile', ['current_weight_kg' => 81.0, 'age' => 44])
        ->assertOk()
        ->assertJsonPath('data.current_weight_kg', 81)
        ->assertJsonPath('data.age', 44)
        ->assertJsonPath('data.first_name', 'Christophe');

    $this->assertDatabaseHas('profiles', ['id' => $profile->id, 'current_weight_kg' => 81.0]);
});

it('retourne 404 à l\'update si aucun profil n\'existe', function () {
    $this->withToken(config('app.static_api_token'))
        ->putJson('/api/profile', ['age' => 44])
        ->assertNotFound();
});

it('rejette les requêtes non authentifiées', function () {
    $this->getJson('/api/profile')->assertUnauthorized();
    $this->postJson('/api/profile', profilePayload())->assertUnauthorized();
    $this->putJson('/api/profile', ['age' => 44])->assertUnauthorized();
});
