<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;

/**
 * Cliente web propio del Servicio de Consulta de CFDI, con su propio timeout (config sat.consulta_cfdi.timeout).
 */
interface WebClientConsultaCfdi extends WebClientInterface {}
