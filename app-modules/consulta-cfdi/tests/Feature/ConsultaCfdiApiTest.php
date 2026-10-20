<?php

use DescargaSat\ClientesApi\Models\ClienteApi;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use Illuminate\Support\Facades\Log;
use Tests\Support\WebClientFalso;

/**
 * Sustituye el cliente web del Servicio de Consulta de CFDI.
 */
function satDeConsulta(): WebClientFalso
{
    $webClient = new WebClientFalso;
    app()->instance(WebClientConsultaCfdi::class, $webClient);

    return $webClient;
}

/**
 * Token de acceso de un Cliente API activo.
 */
function tokenDeClienteApi(): string
{
    return auth('api')->login(ClienteApi::factory()->create());
}

/**
 * Expresión impresa que recibió el SAT en la petición indicada.
 */
function expresionEnviada(WebClientFalso $webClient, int $peticion = 0): string
{
    $sobre = new DOMDocument;
    $sobre->loadXML($webClient->peticiones[$peticion]->getBody());

    return $sobre->getElementsByTagNameNS('http://tempuri.org/', 'expresionImpresa')->item(0)->textContent;
}

test('rejects calls without a valid access token', function () {
    $webClient = satDeConsulta();

    $this->postJson('/api/consulta-cfdi', ['expresion_impresa' => '?re=A&rr=B&tt=1&id=C'])->assertUnauthorized();
    $this->withToken('no.es.un-jwt')->postJson('/api/consulta-cfdi', ['expresion_impresa' => '?re=A&rr=B&tt=1&id=C'])->assertUnauthorized();

    expect($webClient->peticiones)->toBe([]);
});

test('returns the state of a vigente cfdi from its separate data', function () {
    $webClient = satDeConsulta()->responder('consulta-cfdi-vigente.xml');

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', [
            'rfc_emisor' => 'EKU9003173C9',
            'rfc_receptor' => 'XAXX010101000',
            'total' => '1234.50',
            'uuid' => 'ae4b0ca8-8116-4699-8e20-77b6e9c507a2',
        ])
        ->assertOk()
        ->assertExactJson(['data' => [
            'estado' => 'Vigente',
            'es_cancelable' => 'Cancelable con aceptación',
            'estatus_cancelacion' => null,
            'efos' => [
                'emisor_en_lista' => false,
                'validacion' => '200',
                'descripcion' => 'El emisor no está en la lista EFOS',
            ],
            'codigo_estatus' => 'S - Comprobante obtenido satisfactoriamente.',
        ]]);

    expect(expresionEnviada($webClient))->toBe('?re=EKU9003173C9&rr=XAXX010101000&tt=1234.50&id=AE4B0CA8-8116-4699-8E20-77B6E9C507A2');
});

test('accepts the expresion impresa as it comes in the cfdi', function () {
    $webClient = satDeConsulta()->responder('consulta-cfdi-cancelado-efos.xml');
    $expresion = '?re=EKU9003173C9&rr=XAXX010101000&tt=0000001234.500000&id=AE4B0CA8-8116-4699-8E20-77B6E9C507A2';

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', ['expresion_impresa' => $expresion])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Cancelado')
        ->assertJsonPath('data.estatus_cancelacion', 'Cancelado con aceptación')
        ->assertJsonPath('data.efos.emisor_en_lista', true);

    expect(expresionEnviada($webClient))->toBe($expresion);
});

test('a cfdi the sat does not find is a 404', function () {
    satDeConsulta()->responder('consulta-cfdi-no-encontrado.xml');

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', ['expresion_impresa' => '?re=EKU9003173C9&rr=XAXX010101000&tt=1&id=AE4B0CA8-8116-4699-8E20-77B6E9C507A2'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Comprobante no encontrado', 'codigo_estatus' => 'N - 602: Comprobante no encontrado.']);
});

test('an expresion impresa the sat rejects is a 422', function () {
    satDeConsulta()->responder('consulta-cfdi-expresion-invalida.xml');

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', ['expresion_impresa' => 'no es una expresión'])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'La expresión impresa proporcionada no es válida',
            'codigo_estatus' => 'N - 601: La expresión impresa proporcionada no es válida.',
        ]);
});

test('a problem with the sat is a 502', function () {
    satDeConsulta()->sinConexion();
    Log::spy();

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', ['expresion_impresa' => '?re=A&rr=B&tt=1&id=C'])
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $mensaje) => str_starts_with($mensaje, 'No se pudo contactar al SAT'));

    Log::shouldHaveReceived('warning')->once();
});

test('an unknown code of the sat is a 502', function () {
    $vigente = (string) file_get_contents(base_path('tests/Fixtures/sat/consulta-cfdi-vigente.xml'));
    satDeConsulta()->responderTexto(str_replace('S - Comprobante obtenido satisfactoriamente.', 'N - 699: Otro error.', $vigente));

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', ['expresion_impresa' => '?re=A&rr=B&tt=1&id=C'])
        ->assertStatus(502)
        ->assertExactJson(['message' => 'El SAT respondió un código desconocido.', 'codigo_estatus' => 'N - 699: Otro error.']);
});

test('validates the data before asking the sat', function (array $datos, array $errores) {
    $webClient = satDeConsulta();

    $this->withToken(tokenDeClienteApi())
        ->postJson('/api/consulta-cfdi', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errores);

    expect($webClient->peticiones)->toBe([]);
})->with([
    'nothing' => [[], ['expresion_impresa' => 'Envía la expresión impresa, o el RFC emisor, el RFC receptor, el total y el UUID.']],
    'incomplete data' => [['rfc_emisor' => 'EKU9003173C9'], ['rfc_receptor' => 'Falta el RFC receptor.', 'total', 'uuid']],
    'both forms' => [
        ['expresion_impresa' => '?re=A', 'rfc_emisor' => 'EKU9003173C9'],
        ['expresion_impresa' => 'Envía la expresión impresa o los datos por separado, no ambos.'],
    ],
    'malformed data' => [
        ['rfc_emisor' => 'EKU', 'rfc_receptor' => 'XAXX010101000', 'total' => '12.3456789', 'uuid' => 'no-es-uuid'],
        [
            'rfc_emisor' => 'El RFC emisor no tiene un formato válido.',
            'total' => 'El total debe ser un número con hasta 6 decimales.',
            'uuid' => 'El UUID no tiene un formato válido.',
        ],
    ],
]);
