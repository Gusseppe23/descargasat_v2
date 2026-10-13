<?php

use DescargaSat\SatAutenticacion\Contracts\CodigoCancelacion;
use DescargaSat\SatAutenticacion\Contracts\CsdNoVigente;
use DescargaSat\SatAutenticacion\Contracts\FaultDelSat;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use DescargaSat\SatAutenticacion\Contracts\RespuestaNoXml;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\CsdsFalsos;
use Tests\Support\WebClientFalso;

const UUID_A_RESPONDER = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';

/**
 * Sustituye el cliente web del servicio de cancelaciones para responder a ProcesarRespuesta.
 */
function webClientDeRespuestas(): WebClientFalso
{
    $webClient = new WebClientFalso;
    app()->instance(WebClientCancelaciones::class, $webClient);

    return $webClient;
}

/**
 * La SolicitudAceptacionRechazo del sobre enviado, dentro de su documento.
 */
function solicitudEnviada(string $sobre): DOMElement
{
    $documento = new DOMDocument;
    $documento->loadXML($sobre);

    return $documento->getElementsByTagNameNS('http://cancelacfd.sat.gob.mx/entespublicos', 'SolicitudAceptacionRechazo')->item(0);
}

/**
 * Verifica la firma XMLDSig de la solicitud tal como está dentro del sobre: el digest del elemento sin la firma
 * y el valor de la firma sobre el SignedInfo, ambos con c14n exclusiva.
 */
function firmaEsValida(DOMElement $solicitud, string $cer, string $algoritmo): bool
{
    $dsig = 'http://www.w3.org/2000/09/xmldsig#';
    $firma = $solicitud->getElementsByTagNameNS($dsig, 'Signature')->item(0);
    $signedInfo = $firma->getElementsByTagNameNS($dsig, 'SignedInfo')->item(0);
    $digestEsperado = $firma->getElementsByTagNameNS($dsig, 'DigestValue')->item(0)->textContent;
    $valorDeFirma = base64_decode($firma->getElementsByTagNameNS($dsig, 'SignatureValue')->item(0)->textContent);
    $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($cer), 64, "\n").'-----END CERTIFICATE-----';

    $firmaCorrecta = openssl_verify($signedInfo->C14N(true, false), $valorDeFirma, $pem, $algoritmo === 'sha1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256) === 1;

    $solicitud->removeChild($firma);
    $digestCorrecto = base64_encode(hash($algoritmo, $solicitud->C14N(true, false), true)) === $digestEsperado;

    return $firmaCorrecta && $digestCorrecto;
}

test('sends the signed solicitud with the answer to procesar respuesta', function (Respuesta $respuesta) {
    $this->freezeTime();
    CsdsFalsos::usar(CertificadoDePrueba::csd(rfc: 'EKU9003173C9'));
    $webClient = webClientDeRespuestas()->responderAcuse(UUID_A_RESPONDER, $respuesta->value);

    app(ServicioCancelaciones::class)->responder(1, strtolower(UUID_A_RESPONDER), $respuesta);

    $peticion = $webClient->peticiones[0];
    $solicitud = solicitudEnviada($peticion->getBody());
    $folios = $solicitud->getElementsByTagNameNS('http://cancelacfd.sat.gob.mx', 'Folios')->item(0);

    expect($peticion->getHeaders()['SOAPAction'])->toBe('"http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/ProcesarRespuesta"')
        ->and($solicitud->parentNode->localName)->toBe('ProcesarRespuesta')
        ->and($solicitud->getAttribute('Fecha'))->toBe(now('America/Mexico_City')->format('Y-m-d\TH:i:s'))
        ->and($solicitud->hasAttribute('RfcPacEnviaSolicitud'))->toBeTrue()
        ->and($solicitud->getAttribute('RfcPacEnviaSolicitud'))->toBe('')
        ->and($solicitud->getAttribute('RfcReceptor'))->toBe('EKU9003173C9')
        ->and($folios->getElementsByTagNameNS('http://cancelacfd.sat.gob.mx', 'UUID')->item(0)->textContent)->toBe(UUID_A_RESPONDER)
        ->and($folios->getElementsByTagNameNS('http://cancelacfd.sat.gob.mx', 'Respuesta')->item(0)->textContent)->toBe($respuesta->value)
        ->and($solicitud->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->length)->toBe(1);
})->with([Respuesta::Aceptacion, Respuesta::Rechazo]);

test('the signature of the solicitud verifies with the certificate', function (string $algoritmo, string $metodoDeFirma) {
    $certificado = CertificadoDePrueba::csd();
    CsdsFalsos::usar($certificado);
    config(['sat.cancelaciones.firma' => $algoritmo]);
    $webClient = webClientDeRespuestas()->responderAcuse(UUID_A_RESPONDER, 'Rechazo');

    app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Rechazo);

    $solicitud = solicitudEnviada($webClient->peticiones[0]->getBody());
    $signatureMethod = $solicitud->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignatureMethod')->item(0);

    expect($signatureMethod->getAttribute('Algorithm'))->toBe($metodoDeFirma)
        ->and(firmaEsValida($solicitud, $certificado->cer, $algoritmo))->toBeTrue();
})->with([
    'sha256' => ['sha256', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'],
    'sha1' => ['sha1', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1'],
]);

test('a tampered solicitud no longer verifies', function () {
    $certificado = CertificadoDePrueba::csd();
    CsdsFalsos::usar($certificado);
    $webClient = webClientDeRespuestas()->responderAcuse(UUID_A_RESPONDER, 'Rechazo');

    app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Rechazo);

    $solicitud = solicitudEnviada(str_replace('>Rechazo<', '>Aceptacion<', $webClient->peticiones[0]->getBody()));

    expect(firmaEsValida($solicitud, $certificado->cer, 'sha256'))->toBeFalse();
});

test('reads the acuse of the sat with the sent envelope and the received xml', function () {
    CsdsFalsos::usar();
    $webClient = webClientDeRespuestas()->responderAcuse(UUID_A_RESPONDER, 'Aceptacion');

    $acuse = app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Aceptacion);

    expect($acuse->codigo())->toBe(CodigoCancelacion::RespuestaProcesada)
        ->and($acuse->selladoPorElSat)->toBeTrue()
        ->and($acuse->folio(UUID_A_RESPONDER)?->respuesta)->toBe('Aceptacion')
        ->and($acuse->folio(UUID_A_RESPONDER)?->estatusUuid)->toBe('1005')
        ->and($acuse->xmlEnviado)->toBe($webClient->peticiones[0]->getBody())
        ->and($acuse->xmlRecibido)->toContain('Id="SelloSAT"');
});

test('returns the error codes of the sat instead of throwing', function () {
    CsdsFalsos::usar();
    webClientDeRespuestas()->responderAcuse(UUID_A_RESPONDER, 'Rechazo', codigo: '30002', estatus: '30002', sellado: false);

    $acuse = app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Rechazo);

    expect($acuse->codigo())->toBe(CodigoCancelacion::SelloInvalido)
        ->and($acuse->selladoPorElSat)->toBeFalse();
});

test('the problems after sending keep the sent envelope', function (Closure $preparar, string $excepcion) {
    CsdsFalsos::usar();
    $webClient = webClientDeRespuestas();
    $preparar($webClient);

    try {
        app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Rechazo);
        $this->fail('Debió lanzar '.$excepcion);
    } catch (ProblemaConElSat $error) {
        expect($error)->toBeInstanceOf($excepcion)
            ->and($error->xmlEnviado)->toBe($webClient->peticiones[0]->getBody());
    }
})->with([
    'soap fault' => [fn (WebClientFalso $webClient) => $webClient->fallarCon('cancelaciones-fault.xml'), FaultDelSat::class],
    'not xml' => [fn (WebClientFalso $webClient) => $webClient->responderTexto('<html>', 502), RespuestaNoXml::class],
    'no connection' => [fn (WebClientFalso $webClient) => $webClient->sinConexion(), ProblemaConElSat::class],
]);

test('refuses to answer with an expired csd without contacting the sat', function () {
    CsdsFalsos::usar(CertificadoDePrueba::csd());
    $webClient = webClientDeRespuestas();
    $this->travel(5)->years();

    try {
        app(ServicioCancelaciones::class)->responder(1, UUID_A_RESPONDER, Respuesta::Aceptacion);
        $this->fail('Debió lanzar CsdNoVigente');
    } catch (CsdNoVigente $error) {
        expect($error->xmlEnviado)->toBeNull()
            ->and($webClient->peticiones)->toBeEmpty();
    }
});
