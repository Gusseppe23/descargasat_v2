<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\Credentials\Credential;

/**
 * Arma el scraper del portal de CFDI con su propio cliente HTTP (config sat.portal_cfdi). En pruebas se sustituye por un doble.
 */
interface FabricaDeScraper
{
    /**
     * Un scraper nuevo, con sesión propia, que inicia sesión con la credencial cuando se usa.
     */
    public function scraper(Credential $credencial): SatScraperInterface;
}
