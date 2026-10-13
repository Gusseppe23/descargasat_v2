<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;

/**
 * Cliente web propio del servicio de cancelaciones, con su propio timeout (config sat.cancelaciones.timeout).
 */
interface WebClientCancelaciones extends WebClientInterface {}
