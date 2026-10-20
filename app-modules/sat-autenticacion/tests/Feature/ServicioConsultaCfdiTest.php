<?php

use DescargaSat\SatAutenticacion\Contracts\CodigoConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\FaultDelSat;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\RespuestaNoXml;
use DescargaSat\SatAutenticacion\Contracts\ServicioConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\ValidacionEfos;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use Illuminate\Support\Facades\Log;
use Tests\Support\WebClientFalso;

/**
 * Sustituye el cliente web del Servicio de Consulta de CFDI.
 */
function webClientDeConsultaCfdi(): WebClientFalso
{
    $webClient = new WebClientFalso;
    app()->instance(WebClientConsultaCfdi::class, $webClient);

    return $webClient;
}

test('sends the expresion impresa in a plain soap envelope without ws-security', function () {
    config(['sat.consulta_cfdi.url' => 'https://sat.invalid/ConsultaCFDIService.svc']);
    $webClient = webClientDeConsultaCfdi()->responder('consulta-cfdi-vigente.xml');
    $expresion = '?re=EKU9003173C9&rr=XAXX010101000&tt=1234.56&id=AE4B0CA8-8116-4699-8E20-77B6E9C507A2';

    app(ServicioConsultaCfdi::class)->consultar($expresion);

    $peticion = $webClient->peticiones[0];
    $sobre = new DOMDocument;
    $sobre->loadXML($peticion->getBody());
    $xpath = new DOMXPath($sobre);
    $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xpath->registerNamespace('t', 'http://tempuri.org/');

    expect($peticion->getMethod())->toBe('POST')
        ->and($peticion->getUri())->toBe('https://sat.invalid/ConsultaCFDIService.svc')
        ->and($peticion->getHeaders()['SOAPAction'])->toBe('"http://tempuri.org/IConsultaCFDIService/Consulta"')
        ->and($xpath->evaluate('string(/s:Envelope/s:Body/t:Consulta/t:expresionImpresa)'))->toBe($expresion)
        ->and($xpath->evaluate('count(/s:Envelope/s:Header)'))->toEqual(0)
        ->and($peticion->getBody())->not->toContain('Security');
});

test('reads a vigente cfdi', function () {
    webClientDeConsultaCfdi()->responder('consulta-cfdi-vigente.xml');

    $acuse = app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C');

    expect($acuse->codigo())->toBe(CodigoConsultaCfdi::ComprobanteObtenido)
        ->and($acuse->codigoEstatus)->toBe('S - Comprobante obtenido satisfactoriamente.')
        ->and($acuse->estado)->toBe('Vigente')
        ->and($acuse->esCancelable)->toBe('Cancelable con aceptación')
        ->and($acuse->estatusCancelacion)->toBeNull()
        ->and($acuse->efos())->toBe(ValidacionEfos::FueraDeLaLista)
        ->and($acuse->xmlRecibido)->toContain('ConsultaResult');
});

test('reads a cancelled cfdi whose emisor is in the efos list', function () {
    webClientDeConsultaCfdi()->responder('consulta-cfdi-cancelado-efos.xml');

    $acuse = app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C');

    expect($acuse->estado)->toBe('Cancelado')
        ->and($acuse->estatusCancelacion)->toBe('Cancelado con aceptación')
        ->and($acuse->efos())->toBe(ValidacionEfos::EnLaLista);
});

test('a rejection of the sat is an answer, not an error', function (string $respuesta, CodigoConsultaCfdi $codigo) {
    webClientDeConsultaCfdi()->responder($respuesta);

    $acuse = app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C');

    expect($acuse->codigo())->toBe($codigo)
        ->and($acuse->estado)->toBe('No Encontrado')
        ->and($acuse->esCancelable)->toBeNull()
        ->and($acuse->validacionEfos)->toBeNull()
        ->and($acuse->efos())->toBeNull();
})->with([
    'cfdi not found' => ['consulta-cfdi-no-encontrado.xml', CodigoConsultaCfdi::ComprobanteNoEncontrado],
    'invalid expresion impresa' => ['consulta-cfdi-expresion-invalida.xml', CodigoConsultaCfdi::ExpresionInvalida],
]);

test('logs an unknown code and still returns the answer', function () {
    $vigente = (string) file_get_contents(base_path('tests/Fixtures/sat/consulta-cfdi-vigente.xml'));
    webClientDeConsultaCfdi()->responderTexto(str_replace('S - Comprobante obtenido satisfactoriamente.', 'N - 699: Otro error.', $vigente));
    Log::spy();

    $acuse = app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C');

    expect($acuse->codigoEstatus)->toBe('N - 699: Otro error.')
        ->and($acuse->codigo())->toBeNull();
    Log::shouldHaveReceived('warning')->once()->with(
        'El SAT respondió un código desconocido a Consulta.',
        ['codigo' => 'N - 699: Otro error.'],
    );
});

test('a soap fault becomes a fault of the sat', function () {
    webClientDeConsultaCfdi()->fallarCon('cancelaciones-fault.xml');

    expect(fn () => app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C'))
        ->toThrow(FaultDelSat::class, 'El SAT respondió con un error SOAP: An error occurred when verifying security for the message.');
});

test('an empty or non xml answer is reported as such', function () {
    webClientDeConsultaCfdi()->responderTexto('<html><body>Bad Gateway', 502);

    expect(fn () => app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C'))
        ->toThrow(RespuestaNoXml::class, 'El SAT respondió algo que no es XML (HTTP 502).');
});

test('an xml answer without consulta result is a problem with the sat', function () {
    webClientDeConsultaCfdi()->responder('cancelaciones-sin-solicitudes.xml');

    expect(fn () => app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C'))
        ->toThrow(ProblemaConElSat::class, 'La respuesta del SAT (HTTP 200) no trae ConsultaResult.');
});

test('reports when the sat cannot be reached', function () {
    webClientDeConsultaCfdi()->sinConexion();

    expect(fn () => app(ServicioConsultaCfdi::class)->consultar('?re=A&rr=B&tt=1&id=C'))
        ->toThrow(ProblemaConElSat::class, 'No se pudo contactar al SAT');
});

test('reads the code of the codigo estatus as the sat sends it', function (string $codigoEstatus, ?CodigoConsultaCfdi $codigo) {
    expect(CodigoConsultaCfdi::desdeCodigoEstatus($codigoEstatus))->toBe($codigo);
})->with([
    ['S - Comprobante obtenido satisfactoriamente.', CodigoConsultaCfdi::ComprobanteObtenido],
    ['N - 601: La expresión impresa proporcionada no es válida.', CodigoConsultaCfdi::ExpresionInvalida],
    ['N - 602: Comprobante no encontrado.', CodigoConsultaCfdi::ComprobanteNoEncontrado],
    ['N 602', CodigoConsultaCfdi::ComprobanteNoEncontrado],
    ['Sin respuesta', null],
    ['', null],
]);
