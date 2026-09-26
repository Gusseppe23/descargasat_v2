<?php

use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

test('authenticates against the cfdi service with the certificate of the fiel', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $webClient = (new WebClientFalso)->responder('autenticacion.xml');
    app()->instance(WebClientInterface::class, $webClient);

    $token = app(ConexionSat::class)->servicio(1, Servicio::Cfdi)->authenticate();

    expect($token->getValue())->toBe('token-de-prueba')
        ->and($webClient->peticiones)->toHaveCount(1)
        ->and($webClient->peticiones[0]->getUri())->toBe('https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc')
        ->and($webClient->peticiones[0]->getBody())->toContain(base64_encode($certificado->cer));
});

test('authenticates against the retenciones service when asked for retenciones', function () {
    FielesFalsas::usar(CertificadoDePrueba::fiel());
    $webClient = (new WebClientFalso)->responder('autenticacion.xml');
    app()->instance(WebClientInterface::class, $webClient);

    app(ConexionSat::class)->servicio(1, Servicio::Retenciones)->authenticate();

    expect($webClient->peticiones[0]->getUri())->toBe('https://retendescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc');
});

test('refuses to connect with an expired fiel without contacting the sat', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $webClient = new WebClientFalso;
    app()->instance(WebClientInterface::class, $webClient);
    $this->travel(5)->years();

    expect(fn () => app(ConexionSat::class)->servicio(1, Servicio::Cfdi))
        ->toThrow(FielNoVigente::class, 'La Fiel 30001000000500003416 venció el '.$certificado->vigenteHasta->format('d/m/Y').'.')
        ->and($webClient->peticiones)->toBeEmpty();
});
