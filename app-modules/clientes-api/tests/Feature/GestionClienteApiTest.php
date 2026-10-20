<?php

use App\Models\User;
use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Pide un token a la API con las credenciales dadas y devuelve el código de respuesta.
 */
function pedirToken(string $clientId, string $clientSecret): int
{
    auth('api')->forgetUser();
    app('tymon.jwt')->unsetToken();

    return test()->postJson('/api/token', ['client_id' => $clientId, 'client_secret' => $clientSecret])->status();
}

test('guests are redirected to the login page', function () {
    $this->get('/clientes-api')->assertRedirect(route('login'));
});

test('creating a client shows its secret only once and the secret works', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ana López']));

    $pantalla = Livewire::test('clientes-api::gestion')
        ->set('nombre', 'Sistema de Facturación')
        ->call('crear')
        ->assertHasNoErrors();

    $cliente = ClienteApi::sole();
    $secreto = $pantalla->get('secretoGenerado');

    $pantalla->assertSee([$secreto, $cliente->client_id, 'Sistema de Facturación', 'Activo', 'Ana López']);
    expect($cliente->client_secret)->not->toBe($secreto)
        ->and(Hash::check($secreto, $cliente->client_secret))->toBeTrue()
        ->and(pedirToken($cliente->client_id, $secreto))->toBe(200);

    $pantalla->call('cerrarSecreto')->assertDontSee($secreto);
    Livewire::test('clientes-api::gestion')->assertDontSee($secreto);
});

test('requires a name to create a client', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('clientes-api::gestion')
        ->call('crear')
        ->assertHasErrors(['nombre' => 'required']);

    expect(ClienteApi::count())->toBe(0);
});

test('regenerating the secret invalidates the previous one', function () {
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto-anterior')]);

    $this->actingAs(User::factory()->create());

    $nuevo = Livewire::test('clientes-api::gestion')
        ->call('regenerarSecreto', $cliente->id)
        ->assertSee($cliente->client_id)
        ->get('secretoGenerado');

    expect(pedirToken($cliente->client_id, 'secreto-anterior'))->toBe(422)
        ->and(pedirToken($cliente->client_id, $nuevo))->toBe(200);
});

test('deactivating a client prevents it from getting a token and activating restores it', function () {
    $cliente = ClienteApi::factory()->create(['client_secret' => Hash::make('secreto')]);

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('clientes-api::gestion')
        ->call('desactivar', $cliente->id)
        ->assertSee('Inactivo');

    expect(pedirToken($cliente->client_id, 'secreto'))->toBe(422);

    $pantalla->call('activar', $cliente->id);

    expect(pedirToken($cliente->client_id, 'secreto'))->toBe(200);
});

test('deleting a client removes it', function () {
    $cliente = ClienteApi::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('clientes-api::gestion')
        ->call('borrar', $cliente->id)
        ->assertSee('Todavía no hay ningún cliente API.');

    expect(ClienteApi::find($cliente->id))->toBeNull();
});

test('the sidebar links to the clientes api page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('clientes-api.index'))
        ->assertOk()
        ->assertSee('href="'.route('clientes-api.index').'"', escape: false);
});
