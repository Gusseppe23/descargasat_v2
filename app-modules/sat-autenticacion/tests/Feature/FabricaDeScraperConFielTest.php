<?php

use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Services\FabricaDeScraperConFiel;
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
        ->scraper(Credential::create($certificado->cer, $certificado->key, $certificado->contrasena));
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
