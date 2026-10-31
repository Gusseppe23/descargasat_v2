<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\FabricaDeScraper;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\CfdiSatScraper\SatHttpGateway;
use PhpCfdi\CfdiSatScraper\SatScraper;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionManager;
use PhpCfdi\Credentials\Credential;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class FabricaDeScraperConFiel implements FabricaDeScraper
{
    /**
     * @param  array<string, mixed>  $opcionesHttp  Opciones de Guzzle para el portal (timeouts y TLS).
     */
    public function __construct(private array $opcionesHttp) {}

    public function scraper(Credential $credencial, CookieJarInterface $cookies): SatScraperInterface
    {
        /** @var callable|null $handler */
        $handler = $this->opcionesHttp['handler'] ?? null;
        $pila = HandlerStack::create($handler);
        $pila->unshift($this->rechazoDeLaFiel($credencial));

        return new SatScraper(
            FielSessionManager::create($credencial),
            new SatHttpGateway(new Client(['handler' => $pila, RequestOptions::COOKIES => $cookies] + $this->opcionesHttp)),
        );
    }

    /**
     * Cuando el SAT rechaza la Fiel (por ejemplo, revocada), lo avisa en la página de login con e.firma y la librería solo
     * ve después que no hay sesión. Este middleware convierte ese aviso en FielNoVigente para no reportarlo como falla del SAT.
     */
    private function rechazoDeLaFiel(Credential $credencial): callable
    {
        return fn (callable $siguiente): callable => fn (RequestInterface $peticion, array $opciones) => $siguiente($peticion, $opciones)->then(
            function (ResponseInterface $respuesta) use ($peticion, $credencial): ResponseInterface {
                if (! str_contains($peticion->getUri()->getQuery(), 'id=SATx509Custom')) {
                    return $respuesta;
                }

                $html = (string) $respuesta->getBody();
                $respuesta->getBody()->rewind();

                if (preg_match("/var error = '([^']+)'/", $html, $error) === 1) {
                    $numeroCertificado = $credencial->certificate()->serialNumber()->bytes();

                    throw new FielNoVigente("El SAT rechazó la Fiel {$numeroCertificado}: {$error[1]}");
                }

                return $respuesta;
            },
        );
    }
}
