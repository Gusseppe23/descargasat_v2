<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\FabricaDeScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\CfdiSatScraper\SatHttpGateway;
use PhpCfdi\CfdiSatScraper\SatScraper;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionManager;
use PhpCfdi\Credentials\Credential;

class FabricaDeScraperConFiel implements FabricaDeScraper
{
    /**
     * @param  array<string, mixed>  $opcionesHttp  Opciones de Guzzle para el portal (timeouts y TLS).
     */
    public function __construct(private array $opcionesHttp) {}

    public function scraper(Credential $credencial): SatScraperInterface
    {
        return new SatScraper(
            FielSessionManager::create($credencial),
            new SatHttpGateway(new Client([RequestOptions::COOKIES => new CookieJar] + $this->opcionesHttp)),
        );
    }
}
