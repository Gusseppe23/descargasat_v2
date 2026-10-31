<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use GuzzleHttp\Cookie\CookieJarInterface;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\Credentials\Credential;

/**
 * Arma el scraper del portal de CFDI con su propio cliente HTTP (config sat.portal_cfdi). En pruebas se sustituye por un doble.
 */
interface FabricaDeScraper
{
    /**
     * Un scraper que usa la sesión de esas cookies y, si no hay una viva, inicia sesión con la credencial.
     */
    public function scraper(Credential $credencial, CookieJarInterface $cookies): SatScraperInterface;
}
