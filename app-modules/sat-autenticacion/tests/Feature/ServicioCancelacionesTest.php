<?php

use DescargaSat\SatAutenticacion\Contracts\CodigoCancelacion;
use DescargaSat\SatAutenticacion\Contracts\CsdNoVigente;
use DescargaSat\SatAutenticacion\Contracts\FaultDelSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoUtilizable;
use DescargaSat\SatAutenticacion\Contracts\FormatoDeAutorizacionCambiado;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\RespuestaNoXml;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use DescargaSat\SatAutenticacion\Services\SobreCancelaciones;
use Illuminate\Support\Facades\Log;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\CsdsFalsos;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

/**
 * Sustituye el cliente web del servicio de cancelaciones.
 */
function webClientDeCancelaciones(): WebClientFalso
{
    $webClient = new WebClientFalso;
    app()->instance(WebClientCancelaciones::class, $webClient);

    return $webClient;
}

beforeEach(function () {
    FielesFalsas::usar();
});

test('sends the envelope authenticated with the fiel asking for the pendientes of the rfc of the csd', function () {
    CsdsFalsos::usar(CertificadoDePrueba::csd(rfc: 'EKU9003173C9'));
    $fiel = CertificadoDePrueba::fiel(rfc: 'EKU9003173C9');
    FielesFalsas::usar($fiel);
    config(['sat.cancelaciones.url' => 'https://sat.invalid/AceptacionRechazoServiceEP.svc']);
    $webClient = webClientDeCancelaciones()->responder('cancelaciones-sin-solicitudes.xml');

    app(ServicioCancelaciones::class)->pendientes(1);

    $peticion = $webClient->peticiones[0];
    $sobre = new DOMDocument;
    $sobre->loadXML($peticion->getBody());
    $xpath = new DOMXPath($sobre);
    $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xpath->registerNamespace('u', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
    $xpath->registerNamespace('o', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
    $xpath->registerNamespace('ep', 'http://cancelacfd.sat.gob.mx/entespublicos');

    expect($peticion->getMethod())->toBe('POST')
        ->and($peticion->getUri())->toBe('https://sat.invalid/AceptacionRechazoServiceEP.svc')
        ->and($peticion->getHeaders()['SOAPAction'])->toBe('"http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/ObtenerPeticionesPendientes"')
        ->and($xpath->evaluate('count(/s:Envelope/s:Header/o:Security/u:Timestamp)'))->toEqual(1)
        ->and($xpath->evaluate('string(/s:Envelope/s:Header/o:Security/o:BinarySecurityToken)'))->toBe(base64_encode($fiel->cer))
        ->and($xpath->evaluate('string(/s:Envelope/s:Body/ep:ObtenerPeticionesPendientes/ep:RfcReceptor)'))->toBe('EKU9003173C9')
        ->and($peticion->getBody())->not->toContain('Autentica');
});

test('fails clearly when the authorization envelope of the library changes its format', function () {
    expect(fn () => app(SobreCancelaciones::class)->envolver('<s:Envelope><s:Body><Otra/></s:Body></s:Envelope>', '<Cuerpo/>'))
        ->toThrow(FormatoDeAutorizacionCambiado::class);
});

test('reads the uuids of the pendientes reported by the sat', function () {
    CsdsFalsos::usar();
    webClientDeCancelaciones()->responder('cancelaciones-pendientes.xml');

    $pendientes = app(ServicioCancelaciones::class)->pendientes(1);

    expect($pendientes->codigo())->toBe(CodigoCancelacion::HayPendientes)
        ->and($pendientes->uuids)->toBe([
            'AE4B0CA8-8116-4699-8E20-77B6E9C507A2',
            'D92516E5-A881-423C-B40F-384008DFE03F',
        ]);
});

test('an empty list is not an error', function (string $respuesta, CodigoCancelacion $codigo) {
    CsdsFalsos::usar();
    webClientDeCancelaciones()->responder($respuesta);

    $pendientes = app(ServicioCancelaciones::class)->pendientes(1);

    expect($pendientes->codigo())->toBe($codigo)
        ->and($pendientes->uuids)->toBe([]);
})->with([
    'no solicitudes for the rfc' => ['cancelaciones-sin-solicitudes.xml', CodigoCancelacion::SinSolicitudes],
    'no peticiones right now' => ['cancelaciones-sin-peticiones-por-ahora.xml', CodigoCancelacion::SinPeticionesPorAhora],
]);

test('logs an unknown code and still returns the answer', function () {
    CsdsFalsos::usar();
    webClientDeCancelaciones()->responder('cancelaciones-codigo-desconocido.xml');
    Log::spy();

    $pendientes = app(ServicioCancelaciones::class)->pendientes(1);

    expect($pendientes->codEstatus)->toBe('1999')
        ->and($pendientes->codigo())->toBeNull();
    Log::shouldHaveReceived('warning')->once()->with(
        'El SAT respondió un código desconocido a ObtenerPeticionesPendientes.',
        ['codigo' => '1999'],
    );
});

test('a soap fault becomes a fault of the sat', function (int $estado) {
    CsdsFalsos::usar();
    $webClient = webClientDeCancelaciones();
    $estado === 500 ? $webClient->fallarCon('cancelaciones-fault.xml') : $webClient->responder('cancelaciones-fault.xml');

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(FaultDelSat::class, 'El SAT respondió con un error SOAP: An error occurred when verifying security for the message.');
})->with([
    'as http 500' => [500],
    'as http 200' => [200],
]);

test('an empty or non xml answer is reported as such', function (string $cuerpo, string $mensaje) {
    CsdsFalsos::usar();
    webClientDeCancelaciones()->responderTexto($cuerpo, 502);

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(RespuestaNoXml::class, $mensaje);
})->with([
    'empty' => ['', 'El SAT respondió vacío (HTTP 502).'],
    'html' => ['<html><body>Bad Gateway', 'El SAT respondió algo que no es XML (HTTP 502).'],
]);

test('reports when the sat cannot be reached', function () {
    CsdsFalsos::usar();
    webClientDeCancelaciones()->sinConexion();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(ProblemaConElSat::class, 'No se pudo contactar al SAT (Error connecting to https://cancelaciones.sat.invalid/AceptacionRechazoServiceEP.svc).');
});

test('refuses to ask with an expired csd without contacting the sat', function () {
    $certificado = CertificadoDePrueba::csd();
    CsdsFalsos::usar($certificado);
    $webClient = webClientDeCancelaciones();
    $this->travel(5)->years();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(CsdNoVigente::class, 'El CSD 30001000000500003417 venció el '.$certificado->vigenteHasta->format('d/m/Y').'.')
        ->and($webClient->peticiones)->toBeEmpty();
});

test('refuses to ask with a csd that is not valid yet without contacting the sat', function () {
    $certificado = CertificadoDePrueba::csd();
    CsdsFalsos::usar($certificado);
    $webClient = webClientDeCancelaciones();
    $this->travel(-1)->days();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(CsdNoVigente::class, 'El CSD 30001000000500003417 entra en vigor el '.$certificado->vigenteDesde->format('d/m/Y').'.')
        ->and($webClient->peticiones)->toBeEmpty();
});

test('refuses to ask without an active fiel without contacting the sat', function () {
    CsdsFalsos::usar();
    FielesFalsas::usar(hayActiva: false);
    $webClient = webClientDeCancelaciones();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(FielNoUtilizable::class, 'No hay una FIEL activa. El SAT pide la FIEL para autenticar el servicio de cancelaciones; activa una en la pantalla FIEL.')
        ->and($webClient->peticiones)->toBeEmpty();
});

test('refuses to ask with an expired fiel without contacting the sat', function () {
    CsdsFalsos::usar();
    $fiel = CertificadoDePrueba::fiel(diasDeVigencia: 1);
    FielesFalsas::usar($fiel);
    $webClient = webClientDeCancelaciones();
    $this->travel(2)->days();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(FielNoUtilizable::class, 'La FIEL activa 30001000000500003416 no está vigente (del '.$fiel->vigenteDesde->format('d/m/Y').' al '.$fiel->vigenteHasta->format('d/m/Y').').')
        ->and($webClient->peticiones)->toBeEmpty();
});

test('refuses to ask with a fiel of another rfc without contacting the sat', function () {
    CsdsFalsos::usar(CertificadoDePrueba::csd(rfc: 'EKU9003173C9'));
    FielesFalsas::usar(CertificadoDePrueba::fiel(rfc: 'XAXX010101000'));
    $webClient = webClientDeCancelaciones();

    expect(fn () => app(ServicioCancelaciones::class)->pendientes(1))
        ->toThrow(FielNoUtilizable::class, 'La FIEL activa es del RFC XAXX010101000 y el CSD del RFC EKU9003173C9; tienen que ser del mismo RFC.')
        ->and($webClient->peticiones)->toBeEmpty();
});
