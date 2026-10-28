<?php

use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Log;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielLoginException;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionData;
use PhpCfdi\Credentials\Credential;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\FielesFalsas;
use Tests\Support\ScraperFalso;

const UUID_A_DESCARGAR = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';
const XML_A_DESCARGAR = '<cfdi:Comprobante><tfd:TimbreFiscalDigital UUID="AE4B0CA8-8116-4699-8E20-77B6E9C507A2"/></cfdi:Comprobante>';
const PDF_A_DESCARGAR = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
const URL_DEL_XML = 'https://portalcfdi.facturaelectronica.sat.gob.mx/RecuperaCfdi.aspx?Datos=xml';
const URL_DEL_PDF = 'https://portalcfdi.facturaelectronica.sat.gob.mx/RepresentacionImpresa.aspx?Datos=pdf';

/**
 * Token de acceso de un Cliente API activo, para la API de Descarga de CFDI.
 */
function tokenParaDescargar(): string
{
    return auth('api')->login(ClienteApi::factory()->create());
}

/**
 * El portal del SAT con el CFDI de prueba, que ofrece el XML y el PDF indicados.
 *
 * @param  array<string, string>  $datos
 */
function portalConElCfdi(array $datos = []): ScraperFalso
{
    return ScraperFalso::usar()->conCfdi(UUID_A_DESCARGAR, $datos + [
        'urlXml' => URL_DEL_XML,
        'urlPdf' => URL_DEL_PDF,
        'estadoComprobante' => 'Vigente',
    ]);
}

test('rejects calls without a valid access token', function () {
    FielesFalsas::usar();
    $portal = ScraperFalso::usar();

    $this->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])->assertUnauthorized();
    $this->withToken('no.es.un-jwt')->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])->assertUnauthorized();

    expect($portal->credencial)->toBeNull();
});

test('returns the xml and the pdf of the cfdi in base64', function () {
    FielesFalsas::usar();
    $portal = portalConElCfdi()
        ->responderEn(URL_DEL_XML, XML_A_DESCARGAR)
        ->responderEn(URL_DEL_PDF, PDF_A_DESCARGAR);

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => strtolower(UUID_A_DESCARGAR), 'tipo' => 'recibido'])
        ->assertOk()
        ->assertExactJson(['data' => [
            'uuid' => UUID_A_DESCARGAR,
            'tipo' => 'recibido',
            'xml' => base64_encode(XML_A_DESCARGAR),
            'pdf' => base64_encode(PDF_A_DESCARGAR),
            'xml_no_disponible' => null,
            'pdf_no_disponible' => null,
        ]]);

    expect($portal->consultas[0]['tipo']->isRecibidos())->toBeTrue();
});

test('returns only the pdf of a cancelled cfdi with the reason for the missing xml', function () {
    FielesFalsas::usar();
    portalConElCfdi(['urlXml' => '', 'estadoComprobante' => 'Cancelado'])->responderEn(URL_DEL_PDF, PDF_A_DESCARGAR);

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertOk()
        ->assertJsonPath('data.xml', null)
        ->assertJsonPath('data.pdf', base64_encode(PDF_A_DESCARGAR))
        ->assertJsonPath('data.xml_no_disponible', 'El CFDI está cancelado y el portal del SAT no ofrece su XML.')
        ->assertJsonPath('data.pdf_no_disponible', null);
});

test('returns only the xml when the sat does not deliver the pdf, with the reason', function () {
    FielesFalsas::usar();
    portalConElCfdi(['urlPdf' => ''])->responderEn(URL_DEL_XML, XML_A_DESCARGAR);

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertOk()
        ->assertJsonPath('data.xml', base64_encode(XML_A_DESCARGAR))
        ->assertJsonPath('data.pdf', null)
        ->assertJsonPath('data.pdf_no_disponible', 'El portal del SAT no ofrece el PDF de este CFDI.');
});

test('a cfdi the portal does not have is a 404', function () {
    FielesFalsas::usar();
    ScraperFalso::usar();

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'El SAT no tiene el CFDI '.UUID_A_DESCARGAR.' entre los emitidos de la Fiel.']);
});

test('without an active fiel it is a 409 without contacting the sat', function () {
    FielesFalsas::usar(hayActiva: false);
    $portal = ScraperFalso::usar();

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertConflict()
        ->assertExactJson(['message' => 'No hay una FIEL activa para iniciar sesión en el portal del SAT.']);

    expect($portal->credencial)->toBeNull();
});

test('an expired active fiel is a 409 without contacting the sat', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $portal = ScraperFalso::usar();
    $this->travel(5)->years();

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertConflict()
        ->assertExactJson(['message' => 'La Fiel 30001000000500003416 venció el '.$certificado->vigenteHasta->format('d/m/Y').'.']);

    expect($portal->credencial)->toBeNull();
});

test('a cfdi whose files the sat does not deliver is a 422', function () {
    FielesFalsas::usar();
    portalConElCfdi(['urlPdf' => ''])->responderEn(URL_DEL_XML, 'Error', 500);

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $mensaje) => str_starts_with($mensaje, 'El SAT no entregó ni el XML ni el PDF del CFDI '.UUID_A_DESCARGAR.'.'));
});

test('a problem with the sat is a 502', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $credencial = Credential::create($certificado->cer, $certificado->key, $certificado->contrasena);
    ScraperFalso::usar()->fallarConsultaCon(FielLoginException::connectionException('try to login using FIEL', new FielSessionData($credencial)));
    Log::spy();

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', ['uuid' => UUID_A_DESCARGAR, 'tipo' => 'emitido'])
        ->assertStatus(502)
        ->assertExactJson(['message' => 'No se pudo iniciar sesión en el portal del SAT (Connection error when try to login using FIEL).']);

    Log::shouldHaveReceived('warning')->once();
});

test('validates the data before contacting the sat', function (array $datos, array $errores) {
    FielesFalsas::usar();
    $portal = ScraperFalso::usar();

    $this->withToken(tokenParaDescargar())
        ->postJson('/api/descarga-cfdi', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errores);

    expect($portal->credencial)->toBeNull();
})->with([
    'nothing' => [[], ['uuid' => 'Falta el UUID.', 'tipo' => 'Falta el tipo.']],
    'malformed data' => [
        ['uuid' => 'no-es-uuid', 'tipo' => 'ambos'],
        ['uuid' => 'El UUID no tiene un formato válido.', 'tipo' => 'El tipo debe ser emitido o recibido.'],
    ],
]);
