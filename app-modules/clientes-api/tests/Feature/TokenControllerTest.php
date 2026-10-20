<?php

use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:api'])->get('api/prueba-protegida', fn () => ['ok' => true]);
});

test('issues an access token for valid credentials', function () {
    $this->freezeSecond();
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto-correcto')]);

    $this->postJson('/api/token', ['client_id' => $cliente->client_id, 'client_secret' => 'secreto-correcto'])
        ->assertOk()
        ->assertJson(['token_type' => 'Bearer', 'expires_in' => config('jwt.ttl') * 60])
        ->assertJsonStructure(['access_token']);

    expect($cliente->fresh()->ultimo_uso_el->equalTo(now()))->toBeTrue();
});

test('rejects a wrong client secret', function () {
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto-correcto')]);

    $this->postJson('/api/token', ['client_id' => $cliente->client_id, 'client_secret' => 'otro'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id' => 'Credenciales inválidas.'])
        ->assertJsonMissingPath('access_token');
});

test('rejects an inactive client with the same message as an unknown one', function () {
    $cliente = ClienteApi::factory()->inactivo()->create(['client_secret' => Hash::make('secreto-correcto')]);

    $this->postJson('/api/token', ['client_id' => $cliente->client_id, 'client_secret' => 'secreto-correcto'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id' => 'Credenciales inválidas.']);

    $this->postJson('/api/token', ['client_id' => 'ca_no_existe', 'client_secret' => 'secreto-correcto'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id' => 'Credenciales inválidas.']);
});

test('requires the client id and the client secret', function () {
    $this->postJson('/api/token')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'client_secret']);
});

test('an issued token grants access to routes protected by auth:api', function () {
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto-correcto')]);

    $token = $this->postJson('/api/token', ['client_id' => $cliente->client_id, 'client_secret' => 'secreto-correcto'])
        ->json('access_token');

    $this->withToken($token)->getJson('/api/prueba-protegida')->assertOk()->assertJson(['ok' => true]);
});

test('routes protected by auth:api reject a missing, invalid or expired token', function () {
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto-correcto')]);

    $token = $this->postJson('/api/token', ['client_id' => $cliente->client_id, 'client_secret' => 'secreto-correcto'])
        ->json('access_token');
    auth('api')->forgetUser();
    app('tymon.jwt')->unsetToken();

    $this->getJson('/api/prueba-protegida')->assertUnauthorized();
    $this->withToken('no.es.un-jwt')->getJson('/api/prueba-protegida')->assertUnauthorized();

    $this->travel(config('jwt.ttl') + 1)->minutes();

    $this->withToken($token)->getJson('/api/prueba-protegida')->assertUnauthorized();
});
