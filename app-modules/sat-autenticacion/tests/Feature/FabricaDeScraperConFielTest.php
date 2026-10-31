<?php

use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Services\FabricaDeScraperConFiel;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\CfdiSatScraper\Filters\DownloadType;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielLoginException;
use PhpCfdi\Credentials\Credential;
use Tests\Support\CertificadoDePrueba;

/**
 * Un scraper de la fábrica real cuyo cliente HTTP responde, en orden, con estas páginas en lugar del SAT.
 */
function scraperQueRecibe(string ...$paginas): SatScraperInterface
{
    $certificado = CertificadoDePrueba::fiel();
    $respuestas = new MockHandler(array_map(fn (string $pagina): Response => new Response(200, [], $pagina), $paginas));

    return (new FabricaDeScraperConFiel(['handler' => $respuestas]))
        ->scraper(Credential::create($certificado->cer, $certificado->key, $certificado->contrasena), new CookieJar);
}

/**
 * Páginas del portal y de cfdiau hasta pedir el reto que se firma con la Fiel.
 *
 * @return list<string>
 */
function paginasHastaElReto(): array
{
    return [
        '<html>Portal</html>',
        '<html>Acceso por contraseña</html>',
        '<form id="certform"><input name="guid" value="reto"></form>',
    ];
}

test('reuses the session of the given cookies without signing in again', function () {
    $certificado = CertificadoDePrueba::fiel();
    $paginaConSesion = new Response(200, [], '<html>RFC Autenticado: EKU9003173C9</html>');
    $respuestas = new MockHandler([$paginaConSesion, $paginaConSesion]);
    $cookies = new CookieJar(false, [['Name' => 'sesion-del-portal', 'Value' => 'abc', 'Domain' => 'portalcfdi.facturaelectronica.sat.gob.mx']]);

    (new FabricaDeScraperConFiel(['handler' => $respuestas]))
        ->scraper(Credential::create($certificado->cer, $certificado->key, $certificado->contrasena), $cookies)
        ->confirmSessionIsAlive();

    expect($respuestas->count())->toBe(0)
        ->and($respuestas->getLastRequest()?->getHeaderLine('Cookie'))->toBe('sesion-del-portal=abc');
});

test('a fiel the sat rejects as revoked is not a problem with the sat', function () {
    $scraper = scraperQueRecibe(
        ...paginasHastaElReto(),
        ...["<script>\$(function (e) { var error = 'No se puede acceder al aplicativo porque su E.FIRMA está revocada.'; });</script>"],
    );

    expect(fn () => $scraper->listByUuids(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2'], DownloadType::recibidos()))
        ->toThrow(FielNoVigente::class, 'El SAT rechazó la Fiel 30001000000500003416: No se puede acceder al aplicativo porque su E.FIRMA está revocada.');
});

test('a sign in that fails without a fiel error from the sat stays a login failure', function () {
    $scraper = scraperQueRecibe(
        ...paginasHastaElReto(),
        ...[
            "<script>\$(function (e) { var error = ''; });</script><form><input name=\"wa\" value=\"wsignin1.0\"></form>",
            '<html>Portal</html>',
            '<html>Portal sin sesión</html>',
        ],
    );

    expect(fn () => $scraper->listByUuids(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2'], DownloadType::recibidos()))
        ->toThrow(FielLoginException::class);
});
