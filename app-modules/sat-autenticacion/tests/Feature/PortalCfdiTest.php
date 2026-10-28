<?php

use DescargaSat\SatAutenticacion\Contracts\ArchivoNoDisponible;
use DescargaSat\SatAutenticacion\Contracts\CfdiNoEncontrado;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Contracts\PortalCfdi;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;
use DescargaSat\SatAutenticacion\Providers\SatAutenticacionServiceProvider;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielLoginException;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionData;
use PhpCfdi\Credentials\Credential;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\FielesFalsas;
use Tests\Support\ScraperFalso;

const UUID_DEL_PORTAL = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';
const XML_DEL_PORTAL = '<cfdi:Comprobante><tfd:TimbreFiscalDigital UUID="AE4B0CA8-8116-4699-8E20-77B6E9C507A2"/></cfdi:Comprobante>';
const PDF_DEL_PORTAL = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
const URL_XML = 'https://portalcfdi.facturaelectronica.sat.gob.mx/RecuperaCfdi.aspx?Datos=xml';
const URL_PDF = 'https://portalcfdi.facturaelectronica.sat.gob.mx/RepresentacionImpresa.aspx?Datos=pdf';

test('downloads the xml and the pdf of an emitido cfdi signing in with the fiel', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $scraper = ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => URL_XML, 'urlPdf' => URL_PDF, 'estadoComprobante' => 'Vigente'])
        ->responderEn(URL_XML, XML_DEL_PORTAL)
        ->responderEn(URL_PDF, PDF_DEL_PORTAL);

    $archivos = app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido);

    expect($archivos->uuid)->toBe(UUID_DEL_PORTAL)
        ->and($archivos->xml)->toBe(XML_DEL_PORTAL)
        ->and($archivos->pdf)->toBe(PDF_DEL_PORTAL)
        ->and($archivos->xmlNoDisponible)->toBeNull()
        ->and($archivos->pdfNoDisponible)->toBeNull();
    expect($scraper->credencial?->certificate()->serialNumber()->bytes())->toBe('30001000000500003416');
    expect($scraper->consultas)->toHaveCount(1)
        ->and($scraper->consultas[0]['uuids'])->toBe([UUID_DEL_PORTAL])
        ->and($scraper->consultas[0]['tipo']->isEmitidos())->toBeTrue();
});

test('searches among the recibidos when the cfdi is recibido', function () {
    FielesFalsas::usar();
    $scraper = ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => URL_XML, 'urlPdf' => URL_PDF])
        ->responderEn(URL_XML, XML_DEL_PORTAL)
        ->responderEn(URL_PDF, PDF_DEL_PORTAL);

    app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Recibido);

    expect($scraper->consultas[0]['tipo']->isRecibidos())->toBeTrue();
});

test('returns only the pdf of a cancelled cfdi whose xml the portal does not offer', function () {
    FielesFalsas::usar();
    $scraper = ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => '', 'urlPdf' => URL_PDF, 'estadoComprobante' => 'Cancelado'])
        ->responderEn(URL_PDF, PDF_DEL_PORTAL);

    $archivos = app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido);

    expect($archivos->xml)->toBeNull()
        ->and($archivos->pdf)->toBe(PDF_DEL_PORTAL)
        ->and($archivos->xmlNoDisponible)->toBe('El CFDI está cancelado y el portal del SAT no ofrece su XML.')
        ->and($archivos->pdfNoDisponible)->toBeNull();
    expect($scraper->descargas)->toBe([URL_PDF]);
});

test('explains a missing file of a vigente cfdi whose link the portal does not offer', function () {
    FielesFalsas::usar();
    ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => URL_XML, 'urlPdf' => '', 'estadoComprobante' => 'Vigente'])
        ->responderEn(URL_XML, XML_DEL_PORTAL);

    $archivos = app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido);

    expect($archivos->xml)->toBe(XML_DEL_PORTAL)
        ->and($archivos->pdf)->toBeNull()
        ->and($archivos->pdfNoDisponible)->toBe('El portal del SAT no ofrece el PDF de este CFDI.');
});

test('a failed download does not go unnoticed', function (Closure $preparar, string $motivo) {
    FielesFalsas::usar();
    $scraper = ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => URL_XML, 'urlPdf' => URL_PDF, 'estadoComprobante' => 'Vigente'])
        ->responderEn(URL_PDF, PDF_DEL_PORTAL);
    $preparar($scraper);

    $archivos = app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido);

    expect($archivos->xml)->toBeNull()
        ->and($archivos->pdf)->toBe(PDF_DEL_PORTAL)
        ->and($archivos->xmlNoDisponible)->toStartWith('El SAT no entregó el XML (')
        ->and($archivos->xmlNoDisponible)->toContain($motivo);
})->with([
    'http error' => [fn (ScraperFalso $scraper) => $scraper->responderEn(URL_XML, 'Error', 500), 'status code'],
    'not a cfdi' => [fn (ScraperFalso $scraper) => $scraper->responderEn(URL_XML, '<html>Sesión expirada</html>'), 'CFDI'],
    'no connection' => [fn (ScraperFalso $scraper) => $scraper->sinConexionEn(URL_XML), 'Error connecting'],
]);

test('fails when the portal delivers neither the xml nor the pdf', function () {
    FielesFalsas::usar();
    ScraperFalso::usar()
        ->conCfdi(UUID_DEL_PORTAL, ['urlXml' => URL_XML, 'urlPdf' => '', 'estadoComprobante' => 'Cancelado'])
        ->responderEn(URL_XML, 'Error', 500);

    expect(fn () => app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido))
        ->toThrow(ArchivoNoDisponible::class, 'El SAT no entregó ni el XML ni el PDF del CFDI '.UUID_DEL_PORTAL.'.');
});

test('fails when the portal does not have the cfdi without downloading anything', function () {
    FielesFalsas::usar();
    $scraper = ScraperFalso::usar();

    expect(fn () => app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Recibido))
        ->toThrow(CfdiNoEncontrado::class, 'El SAT no tiene el CFDI '.UUID_DEL_PORTAL.' entre los recibidos de la Fiel.')
        ->and($scraper->descargas)->toBeEmpty();
});

test('a failed sign in to the portal is a problem with the sat', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $credencial = Credential::create($certificado->cer, $certificado->key, $certificado->contrasena);
    ScraperFalso::usar()->fallarConsultaCon(FielLoginException::connectionException('try to login using FIEL', new FielSessionData($credencial)));

    expect(fn () => app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido))
        ->toThrow(ProblemaConElSat::class, 'No se pudo iniciar sesión en el portal del SAT (Connection error when try to login using FIEL).');
});

test('refuses to download with an expired fiel without contacting the sat', function () {
    $certificado = CertificadoDePrueba::fiel();
    FielesFalsas::usar($certificado);
    $scraper = ScraperFalso::usar();
    $this->travel(5)->years();

    expect(fn () => app(PortalCfdi::class)->descargar(1, UUID_DEL_PORTAL, TipoCfdi::Emitido))
        ->toThrow(FielNoVigente::class, 'La Fiel 30001000000500003416 venció el '.$certificado->vigenteHasta->format('d/m/Y').'.')
        ->and($scraper->credencial)->toBeNull()
        ->and($scraper->consultas)->toBeEmpty();
});

test('the portal client trusts the intermediate certificate the portal does not send', function () {
    $certificado = openssl_x509_parse((string) file_get_contents(SatAutenticacionServiceProvider::INTERMEDIO_DEL_PORTAL));

    expect($certificado['subject']['CN'])->toBe('GlobalSign RSA OV SSL CA 2018')
        ->and($certificado['issuer']['CN'])->toBe('GlobalSign');
});
